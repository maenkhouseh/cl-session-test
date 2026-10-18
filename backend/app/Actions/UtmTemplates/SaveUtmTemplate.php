<?php

namespace App\Actions\UtmTemplates;

use App\Exceptions\ApiException;
use App\Models\UtmTemplate;
use App\Models\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SaveUtmTemplate
{
    public function __invoke(Workspace $workspace, array $data, ?UtmTemplate $template = null): UtmTemplate
    {
        try {
            return DB::transaction(function () use ($workspace, $data, $template) {
                if ($template) {
                    $template->update($data);

                    return $template;
                }

                return $workspace->utmTemplates()->create($data);
            });
        } catch (UniqueConstraintViolationException) {
            throw ApiException::conflict('A template with this name already exists.');
        }
    }
}
