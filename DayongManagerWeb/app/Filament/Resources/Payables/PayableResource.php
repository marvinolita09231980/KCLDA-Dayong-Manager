<?php

namespace App\Filament\Resources\Payables;

use App\Filament\Resources\Payables\Pages\ListPayables;
use App\Models\Payable;
use App\Services\PayOutstandingPayable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PayableResource extends Resource
{
    protected static ?string $model = Payable::class;
    protected static ?string $navigationLabel = 'Outstanding Payables';
    protected static ?string $pluralModelLabel = 'Outstanding Payables';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';
    protected static string|\UnitEnum|null $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool { return auth()->user()?->hasPermission('disbursements.view') ?? false; }
    public static function canCreate(): bool { return static::canViewAny() && auth()->user()->hasPermission('disbursements.create'); }
    public static function canEdit($record): bool { return static::canViewAny() && ! $record->disbursement_id && auth()->user()->hasPermission('disbursements.edit'); }
    public static function canDelete($record): bool { return static::canViewAny() && ! $record->disbursement_id && auth()->user()->hasPermission('disbursements.delete'); }

    public static function form(Schema $schema): Schema
    {
        $categories = ['Claims', 'Necrological service', 'Transportation expenses', 'Office Supply', 'Meeting', 'Others'];

        return $schema->columns(2)->components([
            TextInput::make('payee')->required()->maxLength(255),
            Select::make('category')->options(array_combine($categories, $categories))->required(),
            Textarea::make('particulars')->label('Description')->required()->columnSpanFull(),
            TextInput::make('amount')->numeric()->prefix('PHP')->required()->minValue(0.01)->maxValue(9999999999.99)->step(0.01)->rules(['decimal:0,2']),
            DatePicker::make('due_date'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query) => $query->with('disbursement'))
            ->description('Already paid a payable? Choose Add to Disbursements and enter the actual payment date and voucher number. The payable will be marked Paid automatically.')
            ->defaultSort('id', 'desc')->columns([
                TextColumn::make('payee')->searchable(),
                TextColumn::make('particulars')->label('Description')->wrap()->searchable(),
                TextColumn::make('category'),
                TextColumn::make('amount')->money('PHP')->sortable(),
                TextColumn::make('due_date')->date()->sortable(),
                TextColumn::make('status')->state(fn (Payable $record) => $record->disbursement_id ? 'Paid' : 'Outstanding')->badge()
                    ->color(fn (string $state) => $state === 'Paid' ? 'success' : 'warning'),
                TextColumn::make('disbursement.disbursement_date')->label('Paid on')->date(),
                TextColumn::make('disbursement.voucher_number')->label('Payment voucher'),
            ])->filters([
                SelectFilter::make('status')->options(['outstanding' => 'Outstanding', 'paid' => 'Paid'])
                    ->default('outstanding')->query(fn (Builder $query, array $data) => $query
                        ->when(($data['value'] ?? null) === 'outstanding', fn ($query) => $query->whereNull('disbursement_id'))
                        ->when(($data['value'] ?? null) === 'paid', fn ($query) => $query->whereNotNull('disbursement_id'))),
            ])->recordActions([
                Action::make('pay')->label('Add to Disbursements')->icon('heroicon-o-banknotes')->button()
                    ->visible(fn (Payable $record) => static::canEdit($record) && static::canCreate())
                    ->modalHeading('Add paid payable to Disbursements')
                    ->modalDescription(fn (Payable $record) => 'Confirm that PHP '.number_format((float) $record->amount, 2).' has been paid to '.$record->payee.'. This creates its disbursement and marks the payable Paid. Use this only if the payment has not already been entered in Disbursements.')
                    ->modalSubmitActionLabel('Add disbursement and mark Paid')
                    ->schema([
                        DatePicker::make('disbursement_date')->label('Payment date')->default(today())->required()->maxDate(today()),
                        TextInput::make('voucher_number')->required()->maxLength(255),
                    ])->action(function (Payable $record, array $data, Action $action): void {
                        app(PayOutstandingPayable::class)->pay($record, $data, auth()->user());
                        $action->sendSuccessNotification();
                    })
                    ->successNotificationTitle('Payable paid and disbursement recorded'),
                EditAction::make()->authorize(fn (Payable $record) => static::canEdit($record)),
                DeleteAction::make()->authorize(fn (Payable $record) => static::canDelete($record))
                    ->modalDescription('Remove this unpaid commitment? No money will be deducted.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPayables::route('/')];
    }
}
