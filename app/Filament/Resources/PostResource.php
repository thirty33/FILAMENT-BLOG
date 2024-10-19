<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'eos-article';

    protected static ?int $navigationSort = 20;

    public static function getLabel(): ?string
    {
        return __('Post');
    }

    public static function getNavigationLabel(): string
    {
        return __('Posts');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Blog');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(
            ! auth()->user()->hasAnyRole(['Admin', 'Editor']),
            fn (Builder $query) => $query->where('user_id', auth()->user()->id),
        );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->columns(3)
                    ->schema([
                        Forms\Components\Section::make()
                            ->columnSpan(2)
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label(__('Título'))
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn(string $operation, $state, Forms\Set $set) =>
                                        $operation === 'create' ? $set('slug', Str::slug($state)) : null
                                    ),
                                Forms\Components\TextInput::make('slug')
                                    ->disabled()
                                    ->dehydrated()
                                    ->required()
                                    ->unique(Post::class, 'slug', ignoreRecord: true),
                                Forms\Components\MarkdownEditor::make('excerpt')
                                    ->label(__('Extracto'))
                                    ->required(),
                                Forms\Components\MarkdownEditor::make('content')
                                    ->label(__('Contenido'))
                                    ->required(),
                                Forms\Components\Select::make('user_id')
                                    ->label(__('Autor'))
                                    ->disabled(fn() => auth()->user()->hasRole('Author') || ! auth()->user()->hasRoles())
                                    ->default(fn() => auth()->id())
                                    ->relationship('author', 'name')
                                    ->options(User::pluck('name', 'id'))
                                    // ->searchable()
                                    ->required(),
                                Forms\Components\Select::make('category_id')
                                    ->hidden(fn() => auth()->user()->hasAnyRole(['Admin', 'Editor']))
                                    ->label(__('Categoría'))
                                    ->relationship('category', 'name')
                                    // ->searchable()
                                    ->required(),
                                Forms\Components\Select::make('category_id')
                                    ->hidden(fn() => ! auth()->user()->hasAnyRole(['Admin', 'Editor']))
                                    ->label(__('Categoría'))
                                    ->relationship('category', 'name')
                                    // ->searchable()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label(__('Nombre'))
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn(string $operation, $state, Forms\Set $set) => $set('slug', Str::slug($state))),
                                        Forms\Components\TextInput::make('slug')
                                            ->disabled()
                                            ->dehydrated()
                                            ->required()
                                            ->unique(Category::class, 'slug', ignoreRecord: true),
                                    ]),
                                Forms\Components\Select::make('tags')
                                    ->relationship('tags', 'name')
                                    ->options(Tag::pluck('name', 'id'))
                                    ->multiple()
                                    ->label(__('Etiquetas'))
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->autofocus()
                                            ->label(__('Nombre'))
                                            ->required()
                                            ->unique(Tag::class, 'name', ignoreRecord: true)
                                    ])
                            ]),

                        Forms\Components\Section::make()
                            ->columnSpan(1)
                            ->schema([
                                Forms\Components\FileUpload::make('image')
                                    ->label(__('Imagen'))
                                    ->directory('posts')
                                    ->image(),
                                Forms\Components\DatePicker::make('published_at')
                                    ->label(__('Publicado en')),
                                Forms\Components\Checkbox::make('published')
                                    ->label(__('Publicado'))
                                    ->default(false),
                                Forms\Components\Placeholder::make('created_at')
                                    ->label(__('Creado'))
                                    ->content(fn(Post $record): ?string => $record->created_at?->diffForHumans())
                                    ->hidden(fn(?Post $record) => $record === null),
                                Forms\Components\Placeholder::make('updated_at')
                                    ->label(__('Actualizado'))
                                    ->content(fn(Post $record): ?string => $record->updated_at?->diffForHumans())
                                    ->hidden(fn(?Post $record) => $record === null),
                            ])
                    ])->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label(__('Imagen')),
                Tables\Columns\TextColumn::make('author.name')
                    ->label(__('Autor'))
                    ->hidden(fn() => auth()->user()->hasRole('Author')),
                Tables\Columns\TextColumn::make('title')
                    ->label(__('Título'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('Categoría'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tags.name')
                    ->label(__('Etiquetas'))
                    ->badge(),
                Tables\Columns\ToggleColumn::make('published')
                    ->label(__('Publicado')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label(__('Categoría'))
                    ->relationship('category', 'name')
                    // ->searchable()
                    ,
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('Autor'))
                    ->relationship('author', 'name')
                    // ->searchable()
                    ->hidden(fn() => auth()->user()->hasRole('Author') || ! auth()->user()->hasRoles()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
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
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
