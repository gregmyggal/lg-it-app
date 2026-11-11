<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApercuStageRequest;
use App\Http\Requests\StoreStageRequest;
use App\Http\Resources\ClasseResource;
use App\Services\StageSessionGenerator;
use Illuminate\Http\JsonResponse;

/** STG-01 : stages planifiés (classes de type « stage »), distincts des fiches marketing `StageController`. */
class ClasseStageController extends Controller
{
    public function __construct(private readonly StageSessionGenerator $generator) {}

    public function apercu(ApercuStageRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->generator->plan($request->validated())]);
    }

    public function store(StoreStageRequest $request): JsonResponse
    {
        $classe = $this->generator->create($request->validated());

        return (new ClasseResource(ClasseController::charger($classe)))->response()->setStatusCode(201);
    }
}
