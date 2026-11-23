<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'color', 'icon', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($c) {
            if (empty($c->slug))             $c->slug       = Str::slug($c->name, '_');
            if ($c->color === null)          $c->color      = '#64748b';
            if ($c->icon === null)           $c->icon       = 'Package';
            if ($c->is_active === null)      $c->is_active  = true;
            if ($c->sort_order === null)     $c->sort_order = 0;
        });
    }

    public function products() { return $this->hasMany(Product::class, 'category_id'); }

    public function scopeActive($q)  { return $q->where('is_active', true); }
    public function scopeOrdered($q) { return $q->orderBy('sort_order')->orderBy('name'); }
}