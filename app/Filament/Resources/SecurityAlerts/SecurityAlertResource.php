<?php

namespace App\Filament\Resources\SecurityAlerts;

use App\Filament\Resources\SecurityAlerts\Pages\EditSecurityAlert;
use App\Filament\Resources\SecurityAlerts\Pages\ListSecurityAlerts;
use App\Filament\Resources\SecurityAlerts\Schemas\SecurityAlertForm;
use App\Filament\Resources\SecurityAlerts\Tables\SecurityAlertsTable;
use App\Models\SecurityAlert;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class SecurityAlertResource extends Resource
{
    protected static ?string $slug = 'security-alerts';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Peringatan Keamanan';

    protected static ?string $modelLabel = 'peringatan keamanan';

    protected static ?string $pluralModelLabel = 'peringatan keamanan';

    protected static ?string $model = SecurityAlert::class;

    protected static ?string $recordTitleAttribute = 'id';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->where('status', 'baru')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return SecurityAlertForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SecurityAlertsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSecurityAlerts::route('/'),
            'edit' => EditSecurityAlert::route('/{record}/edit'),
        ];
    }
}
