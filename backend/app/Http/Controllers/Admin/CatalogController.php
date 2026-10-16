<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CatalogAvailabilityRequest;
use App\Http\Requests\Admin\CatalogRequest;
use App\Http\Resources\Admin\CatalogItemResource;
use App\Services\CatalogReferenceChecker;
use App\Support\ApiResponse;
use App\Support\CatalogRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $definition = CatalogRegistry::forRequest($request);
        $paginator = $definition['model']::query()->orderBy('id')->paginate(50);
        $items = $paginator->getCollection()->map(
            fn ($item) => (new CatalogItemResource($item))->resolve($request),
        );

        return ApiResponse::success([
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(CatalogRequest $request): JsonResponse
    {
        $model = CatalogRegistry::forRequest($request)['model'];
        $record = $model::query()->create($request->validated());

        return ApiResponse::success((new CatalogItemResource($record))->resolve($request), 'Catalog item created.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $model = CatalogRegistry::forRequest($request)['model'];
        $record = $model::query()->findOrFail($id);

        return ApiResponse::success((new CatalogItemResource($record))->resolve($request));
    }

    public function update(CatalogRequest $request, int $id): JsonResponse
    {
        $model = CatalogRegistry::forRequest($request)['model'];
        $record = $model::query()->findOrFail($id);
        $record->fill($request->validated())->save();

        return ApiResponse::success((new CatalogItemResource($record->refresh()))->resolve($request), 'Catalog item updated.');
    }

    public function destroy(Request $request, int $id, CatalogReferenceChecker $references): JsonResponse
    {
        $model = CatalogRegistry::forRequest($request)['model'];
        $record = $model::query()->findOrFail($id);

        if ($references->isReferenced($record)) {
            return ApiResponse::error('This catalog item is referenced and cannot be deleted.', status: 409);
        }

        try {
            $record->delete();
        } catch (QueryException) {
            return ApiResponse::error('This catalog item is referenced and cannot be deleted.', status: 409);
        }

        return ApiResponse::success(null, 'Catalog item deleted.');
    }

    public function availability(CatalogAvailabilityRequest $request, int $id): JsonResponse
    {
        $definition = CatalogRegistry::forRequest($request);
        $field = $definition['availability'];
        if ($field === null) {
            return ApiResponse::error('Availability cannot be changed for this catalog type.', status: 422);
        }

        $record = $definition['model']::query()->findOrFail($id);
        $record->forceFill([$field => $request->validated($field)])->save();

        return ApiResponse::success(
            (new CatalogItemResource($record->refresh()))->resolve($request),
            'Availability updated.',
        );
    }
}
