<?php

namespace App\Filament\Resources\PaymentOptions;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\PaymentOptions\Pages\ManagePaymentOptions;
use App\Filament\Support\SafeUrl;
use App\Models\PaymentOption;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/** The ways students can pay: online checkout links, invoice by email, bank transfer. Enabled ones are shown on the Services and refund pages. */
class PaymentOptionResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = PaymentOption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'payment option';

    protected static ?string $navigationLabel = 'Payment options';

    protected static function permission(): string
    {
        return 'site.manage';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120)->helperText('For example "Pay online", "Invoice by email" or "Bank transfer".'),
            Select::make('kind')->options(PaymentOption::KINDS)->required()->default('invoice')->live(),
            Textarea::make('instructions')->rows(4)->maxLength(2000)->columnSpanFull()->helperText('What the student should do. Do not put secrets or account passwords here: this text is public.'),
            TextInput::make('link_url')->label('Payment link')->url()->rules(SafeUrl::rules())->maxLength(500)->visible(fn (Get $get) => $get('kind') === 'online')
                ->helperText('The checkout or payment-request link (for example from Stripe, Alipay or WeChat Pay). Shown as a "Pay online" button.'),
            Toggle::make('enabled')->default(true)->helperText('Only enabled options are shown to students.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('kind')->badge()->formatStateUsing(fn ($s) => PaymentOption::KINDS[$s] ?? $s),
                TextColumn::make('instructions')->limit(60),
                ToggleColumn::make('enabled')->disabled(fn () => ! static::allowed()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('sort')->reorderable('sort');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePaymentOptions::route('/')];
    }
}
