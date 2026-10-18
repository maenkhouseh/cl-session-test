<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Domains\CreateDomain;
use App\Actions\Domains\DeleteDomain;
use App\Actions\Domains\UpdateDomain;
use App\Actions\Domains\VerifyDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDomainRequest;
use App\Http\Resources\DomainResource;
use App\Models\Domain;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DomainController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    public function index(): AnonymousResourceCollection
    {
        return DomainResource::collection($this->current->workspace()->domains()->orderByDesc('is_primary')->orderBy('slug')->get());
    }

    public function store(SaveDomainRequest $request, CreateDomain $create): JsonResponse
    {
        $domain = $create($this->current->workspace(), $request->validated('slug'), $this->options($request->validated()));

        return (new DomainResource($domain))->response()->setStatusCode(201);
    }

    public function update(SaveDomainRequest $request, Domain $domain, UpdateDomain $update): DomainResource
    {
        return new DomainResource($update($domain, $this->options($request->validated())));
    }

    public function verify(Domain $domain, VerifyDomain $verify): DomainResource
    {
        return new DomainResource($verify($domain));
    }

    public function destroy(Domain $domain, DeleteDomain $delete): JsonResponse
    {
        $delete($domain);

        return response()->json(['id' => $domain->id]);
    }

    /** API field names → column names, only for keys the caller sent. */
    private function options(array $data): array
    {
        $map = ['primary' => 'is_primary', 'archived' => 'archived', 'placeholder' => 'placeholder_url', 'notFoundUrl' => 'not_found_url', 'expiredUrl' => 'expired_url'];

        return collect($map)->filter(fn ($column, $field) => array_key_exists($field, $data))
            ->mapWithKeys(fn ($column, $field) => [$column => $data[$field]])->all();
    }
}
