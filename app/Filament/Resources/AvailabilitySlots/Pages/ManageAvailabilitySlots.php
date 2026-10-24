<?php

namespace App\Filament\Resources\AvailabilitySlots\Pages;

use App\Domain\Counselling\Booking;
use App\Filament\Resources\AvailabilitySlots\AvailabilitySlotResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use InvalidArgumentException;

class ManageAvailabilitySlots extends ManageRecords
{
    protected static string $resource = AvailabilitySlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addSlots')->label('Add free times')->icon('heroicon-o-plus')
                ->modalHeading('When are you free?')->modalDescription(fn () => 'Times are in your own time zone ('.(auth()->user()->timezone ?: 'UTC').'). They are split into calls of the length you choose.')
                ->schema([
                    DatePicker::make('date')->required()->native(false)->minDate(today()),
                    TextInput::make('from')->label('From')->type('time')->required()->default('09:00'),
                    TextInput::make('to')->label('Until')->type('time')->required()->default('12:00'),
                    Select::make('minutes')->label('Length of each call')->options([15 => '15 minutes', 30 => '30 minutes', 45 => '45 minutes', 60 => '60 minutes'])->default(30)->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $added = app(Booking::class)->addSlots(auth()->user(), $data['date'], $data['from'], $data['to'], (int) $data['minutes']);
                        Notification::make()->title($added.' free '.str('time')->plural($added).' added')->success()->send();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
