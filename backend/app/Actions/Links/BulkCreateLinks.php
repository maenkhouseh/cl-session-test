<?php

namespace App\Actions\Links;

use App\Exceptions\ApiException;
use App\Http\Requests\Rules\LinkRules;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Independent per-item creation: failures are reported in place and never abort the batch. */
class BulkCreateLinks
{
    public function __construct(private CreateLink $create) {}

    /** @param array<int, array<string, mixed>> $items @return array<int, array{link?: \App\Models\Link, error?: array{code: string, message: string}}> */
    public function __invoke(Workspace $workspace, ?User $user, array $items): array
    {
        return array_map(function (array $item) use ($workspace, $user) {
            try {
                $validated = Validator::make($item, LinkRules::store())->validate();

                return ['link' => ($this->create)($workspace, $user, $validated)];
            } catch (ValidationException $e) {
                return ['error' => ['code' => 'unprocessable_entity', 'message' => $e->validator->errors()->first()]];
            } catch (ApiException $e) {
                return ['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]];
            }
        }, $items);
    }
}
