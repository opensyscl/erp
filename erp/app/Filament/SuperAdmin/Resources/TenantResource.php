<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\TenantResource\Pages;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Tiendas';

    protected static ?string $modelLabel = 'Tienda';

    protected static ?string $pluralModelLabel = 'Tiendas';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Tienda')
                    ->description('Configura los datos básicos de la tienda')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la Tienda')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->alphaDash()
                            ->helperText('Identificador único para la URL (ej: mi-tienda)'),

                        Forms\Components\TextInput::make('domain')
                            ->label('Dominio Personalizado')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Opcional. Ej: tienda.com')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'active' => 'Activa',
                                'inactive' => 'Inactiva',
                                'suspended' => 'Suspendida',
                            ])
                            ->default('active')
                            ->required(),

                        Forms\Components\Select::make('plan')
                            ->label('Plan')
                            ->options([
                                'free' => 'Gratis',
                                'basic' => 'Básico',
                                'pro' => 'Profesional',
                                'enterprise' => 'Empresarial',
                            ])
                            ->default('free')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Apariencia')
                    ->description('Personaliza los colores de la tienda')
                    ->schema([
                        Forms\Components\ColorPicker::make('settings.brand_color')
                            ->label('Color de Marca')
                            ->default('#3b82f6')
                            ->required(),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Administrador Inicial')
                    ->description('Crea el usuario administrador de esta tienda')
                    ->schema([
                        Forms\Components\TextInput::make('admin_name')
                            ->label('Nombre del Admin')
                            ->required()
                            ->maxLength(255)
                            ->visibleOn('create'),

                        Forms\Components\TextInput::make('admin_email')
                            ->label('Email del Admin')
                            ->email()
                            ->required()
                            ->unique('users', 'email')
                            ->maxLength(255)
                            ->visibleOn('create'),

                        Forms\Components\TextInput::make('admin_password')
                            ->label('Contraseña')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->visibleOn('create'),
                    ])
                    ->columns(2)
                    ->visibleOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'suspended',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Activa',
                        'inactive' => 'Inactiva',
                        'suspended' => 'Suspendida',
                        default => $state,
                    }),

                Tables\Columns\BadgeColumn::make('plan')
                    ->label('Plan')
                    ->colors([
                        'gray' => 'free',
                        'info' => 'basic',
                        'primary' => 'pro',
                        'success' => 'enterprise',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'free' => 'Gratis',
                        'basic' => 'Básico',
                        'pro' => 'Profesional',
                        'enterprise' => 'Empresarial',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->counts('users')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active' => 'Activa',
                        'inactive' => 'Inactiva',
                        'suspended' => 'Suspendida',
                    ]),

                Tables\Filters\SelectFilter::make('plan')
                    ->label('Plan')
                    ->options([
                        'free' => 'Gratis',
                        'basic' => 'Básico',
                        'pro' => 'Profesional',
                        'enterprise' => 'Empresarial',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('acceder')
                    ->label('Acceder')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->url(fn (Tenant $record): string => "/app/{$record->slug}")
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('toggle_status')
                    ->label(fn (Tenant $record): string => $record->status === 'active' ? 'Suspender' : 'Activar')
                    ->icon(fn (Tenant $record): string => $record->status === 'active' ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (Tenant $record): string => $record->status === 'active' ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Tenant $record): void {
                        $record->update([
                            'status' => $record->status === 'active' ? 'suspended' : 'active',
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
