<?php

namespace App\Filament\Resources\PartnerOrganisations\RelationManagers;

use App\Domain\Editorial\AuditLog;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The people at a partner who can sign in to the partner area. Created here by staff; there is no public partner sign-up. */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'People who can sign in';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('viewAny', $ownerRecord::class);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('email')->email()->required()->maxLength(190)->unique(User::class, 'email'),
            TextInput::make('password')->password()->revealable()->required()->minLength(12)->helperText('At least 12 characters. Give it to the partner securely; they can change it after signing in. At their first sign-in they must also type a 6-digit code emailed to the address above, so check it is spelled correctly.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name'), TextColumn::make('email'), TextColumn::make('status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'danger')])
            ->headerActions([
                CreateAction::make()->label('Add a person')->using(function (array $data): User {
                    abort_unless(auth()->user()->can('create', $this->getOwnerRecord()::class), 403);
                    $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
                    $user->forceFill(['partner_organisation_id' => $this->getOwnerRecord()->getKey(), 'status' => 'active'])->save(); // not verified yet: at first sign-in they type the code emailed to this address
                    $user->userRoles()->create(['role' => 'partner_manager']);
                    AuditLog::record('partner.user_created', $user, null, ['organisation_id' => $this->getOwnerRecord()->getKey()]);

                    return $user;
                }),
            ])
            ->recordActions([
                Action::make('suspend')->label('Suspend')->color('danger')->requiresConfirmation()->visible(fn (User $r) => $r->status === 'active')
                    ->action(function (User $r) {
                        abort_unless(auth()->user()->can('create', $this->getOwnerRecord()::class), 403);
                        $r->suspend();
                        Notification::make()->title('Suspended and signed out everywhere')->success()->send();
                    }),
            ]);
    }
}
