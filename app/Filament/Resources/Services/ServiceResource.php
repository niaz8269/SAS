<?php

namespace App\Filament\Resources\Services;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\Services\Pages\ManageServices;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** SVC-01. Leave price and turnaround blank until the owner approves them: the page then says "Request a quotation". */
class ServiceResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static function permission(): string
    {
        return 'services.manage';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
            Select::make('category')->options(config('sas.enquiry_categories'))->required(),
            TextInput::make('team'),
            Textarea::make('summary')->rows(2)->columnSpanFull(),
            Textarea::make('audience')->rows(2)->columnSpanFull(),
            RichEditor::make('description')->columnSpanFull(),
            TagsInput::make('deliverables')->placeholder('Type and press Enter')->columnSpanFull(),
            TagsInput::make('required_inputs')->columnSpanFull(),
            TagsInput::make('process_steps')->columnSpanFull(),
            TagsInput::make('exclusions')->helperText('What is NOT included. Always state the limits (no guarantee of selection, admission or visa).')->columnSpanFull(),
            TextInput::make('price_basis')->helperText('Owner-approved only. Empty = "Request a quotation".'),
            TextInput::make('turnaround_basis')->helperText('Owner-approved only.'),
            TextInput::make('revision_limit')->numeric()->minValue(0),
            Toggle::make('is_available')->default(true),
            Toggle::make('published'),
            Toggle::make('featured')->label('Featured on the homepage'),
            TextInput::make('sort')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(), TextColumn::make('category')->badge(),
            IconColumn::make('published')->boolean(), IconColumn::make('featured')->boolean(),
            TextColumn::make('price_basis')->placeholder('Request a quotation'),
        ])->recordActions([EditAction::make(), DeleteAction::make()])->defaultSort('sort')->reorderable('sort');
    }

    public static function getPages(): array
    {
        return ['index' => ManageServices::route('/')];
    }
}
