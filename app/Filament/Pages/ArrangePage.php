<?php

namespace App\Filament\Pages;

use App\Domain\Editorial\AuditLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Staff decide the order a list appears in on the website: drag a row, or send it to the top, the bottom or to any position number.
 * Until someone starts arranging, the list keeps its automatic order; the first arrangement pins today's order as positions 1, 2, 3 and so on.
 * Items added later have no position yet and appear after the placed ones until they are placed too.
 */
abstract class ArrangePage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected string $view = 'filament.pages.arrange';

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** Every row that is listed on the website, in the order the website shows them now. */
    abstract protected function ordered(): Builder;

    /** @return list<Column> */
    abstract protected function nameColumns(): array;

    abstract public function intro(): string;

    abstract protected function noun(): string;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('site.manage');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /** True when every listed row has a position, so dragging is safe. */
    public function isArranged(): bool
    {
        return ! (clone $this->ordered())->whereNull($this->table_name().'.sort_order')->exists();
    }

    public function totalCount(): int
    {
        return (clone $this->ordered())->count();
    }

    public function unplacedCount(): int
    {
        return (clone $this->ordered())->whereNull($this->table_name().'.sort_order')->count();
    }

    private function table_name(): string
    {
        return (new ($this->modelClass()))->getTable();
    }

    public function table(Table $table): Table
    {
        $count = (clone $this->ordered())->count();

        return $table
            ->query($this->ordered())
            ->columns([TextColumn::make('position')->label('Position')->rowIndex(), ...$this->nameColumns()])
            ->paginated(false)
            ->reorderable('sort_order', fn () => $this->isArranged())
            ->recordActions([
                Action::make('top')->label('To top')->icon(Heroicon::OutlinedArrowUpCircle)->iconButton()->tooltip('Show first')
                    ->action(fn (Model $record) => $this->place($record->getKey(), 1)),
                Action::make('bottom')->label('To bottom')->icon(Heroicon::OutlinedArrowDownCircle)->iconButton()->tooltip('Show last')
                    ->action(fn (Model $record) => $this->place($record->getKey(), PHP_INT_MAX)),
                Action::make('position')->label('Move to position…')->icon(Heroicon::OutlinedHashtag)->iconButton()->tooltip('Move to a position number')
                    ->modalHeading('Move to a position')->modalWidth('sm')
                    ->fillForm(fn (Model $record) => ['position' => $this->positionOf($record->getKey())])
                    ->schema([TextInput::make('position')->label('Position (1 is first, '.$count.' is last)')->numeric()->integer()->minValue(1)->maxValue($count)->required()])
                    ->action(fn (array $data, Model $record) => $this->place($record->getKey(), (int) $data['position'])),
            ])
            ->headerActions([
                Action::make('pin')->label('Start arranging (keep today’s order)')->icon(Heroicon::OutlinedLockClosed)
                    ->visible(fn () => ! $this->isArranged())
                    ->modalDescription('Today’s order on the website becomes positions 1, 2, 3 and so on, and you can then drag rows. Items you add later appear after these until you place them.')
                    ->requiresConfirmation()
                    ->action(function () {
                        $this->writeOrder((clone $this->ordered())->pluck($this->table_name().'.id')->all());
                        Notification::make()->title('You can now drag rows to rearrange')->success()->send();
                    }),
                Action::make('reset')->label('Back to automatic order')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                    ->requiresConfirmation()->modalDescription('Removes every position you set. The website goes back to its automatic order.')
                    ->action(function () {
                        $this->modelClass()::query()->update(['sort_order' => null]);
                        AuditLog::record('site.order_reset', null, null, ['list' => $this->noun()]);
                        $this->flushCachedTableRecords();
                        Notification::make()->title('Automatic order restored')->success()->send();
                    }),
            ])
            ->afterReordering(function () {
                AuditLog::record('site.order_changed', null, null, ['list' => $this->noun(), 'by' => 'drag and drop']);
            })
            ->emptyStateHeading('Nothing is listed on the website yet');
    }

    /** 1-based position of a row in the order the website shows now. */
    public function positionOf(int|string $id): int
    {
        $ids = (clone $this->ordered())->pluck($this->table_name().'.id')->all();
        $at = array_search((int) $id, array_map('intval', $ids), true);

        return $at === false ? 1 : $at + 1;
    }

    /** Moves one row to a position (1 is first); anything past the end means last. Everything else keeps its relative order. */
    public function place(int|string $id, int $position): void
    {
        abort_unless(static::canAccess(), 403);
        $ids = array_map('intval', (clone $this->ordered())->pluck($this->table_name().'.id')->all());
        $id = (int) $id;
        $ids = array_values(array_diff($ids, [$id]));
        array_splice($ids, max(0, min(count($ids), $position - 1)), 0, [$id]);
        $this->writeOrder($ids);
        AuditLog::record('site.order_changed', null, null, ['list' => $this->noun(), 'moved' => $id, 'to' => min($position, count($ids))]);
        Notification::make()->title('Moved to position '.min($position, count($ids)))->success()->send();
    }

    /** @param list<int> $ids */
    public function writeOrder(array $ids): void
    {
        abort_unless(static::canAccess(), 403);
        $table = $this->table_name();
        DB::transaction(function () use ($ids, $table) {
            foreach (array_chunk(array_values($ids), 400, true) as $chunk) {
                $case = 'case id';
                foreach ($chunk as $index => $id) {
                    $case .= ' when '.(int) $id.' then '.($index + 1);
                }
                DB::table($table)->whereIn('id', array_values($chunk))->update(['sort_order' => DB::raw($case.' end')]);
            }
        });
        $this->flushCachedTableRecords();
    }
}
