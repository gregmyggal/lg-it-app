<?php

namespace App\Http\Controllers;

use App\Models\Anniversaire;
use App\Models\ClasseSetting;
use App\Models\Cours;
use App\Models\Formation;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClasseSettingController extends Controller
{
    private const PARENT_TYPES = [
        'cours' => Cours::class,
        'stages' => Stage::class,
        'formations' => Formation::class,
        'anniversaires' => Anniversaire::class,
    ];

    public function show(string $parentType, int $parentId)
    {
        $parent = $this->resolveParent($parentType, $parentId);

        $setting = ClasseSetting::firstOrNew([
            'parent_type' => $parent->getMorphClass(),
            'parent_id' => $parent->id,
        ]);

        Gate::authorize('view', $setting);

        return $setting;
    }

    public function update(Request $request, string $parentType, int $parentId)
    {
        $parent = $this->resolveParent($parentType, $parentId);

        $data = $request->validate([
            'seance_active' => ['nullable', 'string', 'max:255'],
            'access_code' => ['nullable', 'string', 'max:12'],
        ]);

        $setting = ClasseSetting::firstOrNew([
            'parent_type' => $parent->getMorphClass(),
            'parent_id' => $parent->id,
        ]);

        if ($setting->exists) {
            Gate::authorize('update', $setting);
        } else {
            Gate::authorize('createFor', [ClasseSetting::class, $parent]);
        }

        $setting->fill($data);
        $setting->parent_type = $parent->getMorphClass();
        $setting->parent_id = $parent->id;
        $setting->save();

        return $setting;
    }

    private function resolveParent(string $parentType, int $parentId): Model
    {
        abort_unless(array_key_exists($parentType, self::PARENT_TYPES), 404);

        return self::PARENT_TYPES[$parentType]::findOrFail($parentId);
    }
}
