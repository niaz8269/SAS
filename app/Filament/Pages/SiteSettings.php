<?php

namespace App\Filament\Pages;

use App\Domain\Editorial\AuditLog;
use App\Domain\Site\Site;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\SafeUrl;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Site-wide values: brand, contact details, footer note, announcement bar, social links, logo.
 * Empty fields fall back to the built-in defaults, so nothing can leave a page blank.
 *
 * @property-read Schema $form
 */
class SiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Site settings';

    protected static ?string $slug = 'site-settings';

    protected string $view = 'filament.pages.site-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('site.manage');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $values = Site::all();
        $values['announcement_enabled'] = ($values['announcement_enabled'] ?? '0') === '1';
        $this->form->fill($values + array_fill_keys(array_keys(Site::FIELDS), null));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Brand')->description('Shown in the header, footer, page titles and emails. Leave blank to use the built-in wording.')->columns(2)->schema([
                TextInput::make('brand_name')->label('Site name')->placeholder(config('sas.brand.name'))->maxLength(120),
                TextInput::make('brand_short')->label('Short name (logo text)')->placeholder(config('sas.brand.short'))->maxLength(30),
                TextInput::make('tagline')->placeholder(config('sas.brand.tagline'))->maxLength(160)->columnSpanFull(),
                MediaPicker::make('logo_path')->label('Logo image (optional)')->columnSpanFull()
                    ->helperText('Replaces the built-in leaf mark in the header. Pick from the media library or use the + to upload.'),
                Textarea::make('default_meta_description')->label('Default search-engine description')->rows(2)->maxLength(300)->columnSpanFull(),
            ]),
            Section::make('Contact and legal')->columns(2)->schema([
                TextInput::make('contact_email')->email()->placeholder(config('sas.contact_email')),
                TextInput::make('accessibility_email')->email()->placeholder(config('sas.accessibility_email')),
                TextInput::make('contact_phone')->tel()->maxLength(40),
                TextInput::make('legal_entity')->label('Legal entity line')->placeholder(config('sas.legal_entity'))->maxLength(200),
                Textarea::make('contact_address')->label('Postal address')->rows(2)->maxLength(300)->columnSpanFull(),
                Textarea::make('footer_note')->label('Footer note (extra line)')->rows(2)->maxLength(400)->columnSpanFull(),
            ]),
            Section::make('Announcement bar')->description('A thin banner across the top of every public page.')->columns(2)->schema([
                Toggle::make('announcement_enabled')->label('Show the announcement bar')->columnSpanFull(),
                TextInput::make('announcement_text')->maxLength(200),
                TextInput::make('announcement_url')->label('Link (optional)')->rules(SafeUrl::rules())->maxLength(500),
            ]),
            Section::make('Social links')->description('Full https:// addresses. Empty ones are not shown.')->columns(2)->schema([
                TextInput::make('social_facebook')->url(),
                TextInput::make('social_x')->label('X / Twitter')->url(),
                TextInput::make('social_instagram')->url(),
                TextInput::make('social_linkedin')->url(),
                TextInput::make('social_youtube')->url(),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getFormContentComponent()]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([Actions::make([
                Action::make('save')->label('Save settings')->submit('save')->keyBindings(['mod+s']),
            ])->key('form-actions')]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        $before = Site::all();
        Site::save($data);
        AuditLog::record('site.settings_saved', null, array_intersect_key($before, $data), array_filter($data, fn ($v) => $v !== null));
        Notification::make()->title('Settings saved')->success()->send();
    }
}
