<?php

declare(strict_types=1);

namespace Cortex\Tenants\Http\Controllers\Adminarea;

use Illuminate\Support\Str;
use Rinvex\Tenants\Models\Tenant;
use Spatie\MediaLibrary\Models\Media;
use Cortex\Foundation\DataTables\MediaDataTable;
use Cortex\Foundation\Http\Requests\ImageFormRequest;
use Cortex\Foundation\Http\Controllers\AuthorizedController;

class TenantsMediaController extends AuthorizedController
{
    /**
     * {@inheritdoc}
     */
    protected $resource = 'tenant';

    /**
     * {@inheritdoc}
     */
    public function authorizeResource($model, $parameter = null, array $options = [], $request = null): void
    {
        $middleware = [];
        $parameter = $parameter ?: Str::snake(class_basename($model));

        foreach ($this->mapResourceAbilities() as $method => $ability) {
            $modelName = in_array($method, $this->resourceMethodsWithoutModels()) ? $model : $parameter;

            $middleware["can:update,{$modelName}"][] = $method;
            $middleware["can:{$ability},media"][] = $method;
        }

        foreach ($middleware as $middlewareName => $methods) {
            $this->middleware($middlewareName, $options)->only($methods);
        }
    }

    /**
     * Get a listing of the resource media.
     *
     * @param \Rinvex\Tenants\Models\Tenant $tenant
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function index(Tenant $tenant, MediaDataTable $mediaDataTable)
    {
        return $mediaDataTable->with([
            'resource' => $tenant,
            'tabs' => 'adminarea.tenants.tabs',
            'phrase' => trans('cortex/tenants::common.tenants'),
            'id' => "adminarea-tenants-{$tenant->getKey()}-media-table",
            'url' => route('adminarea.tenants.media.store', ['tenant' => $tenant]),
        ])->render('cortex/foundation::adminarea.pages.datatable-media');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Cortex\Foundation\Http\Requests\ImageFormRequest $request
     * @param \Rinvex\Tenants\Models\Tenant                     $tenant
     *
     * @return void
     */
    public function store(ImageFormRequest $request, Tenant $tenant): void
    {
        $tenant->addMediaFromRequest('file')
               ->sanitizingFileName(function ($fileName) {
                   return md5($fileName).'.'.pathinfo($fileName, PATHINFO_EXTENSION);
               })
               ->toMediaCollection('default', config('cortex.tenants.media.disk'));
    }

    /**
     * Delete the given resource from storage.
     *
     * @param \Rinvex\Tenants\Models\Tenant     $tenant
     * @param \Spatie\MediaLibrary\Models\Media $media
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function delete(Tenant $tenant, Media $media)
    {
        $tenant->media()->where($media->getKeyName(), $media->getKey())->first()->delete();

        return intend([
            'url' => route('adminarea.tenants.media.index', ['tenant' => $tenant]),
            'with' => ['warning' => trans('cortex/foundation::messages.resource_deleted', ['resource' => 'media', 'id' => $media->getKey()])],
        ]);
    }
}
