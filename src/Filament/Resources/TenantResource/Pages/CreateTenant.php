<?php

namespace TomatoPHP\FilamentTenancy\Filament\Resources\TenantResource\Pages;

use Exception;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Artisan;
use Log;
use TomatoPHP\FilamentTenancy\Filament\Resources\TenantResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Facades\FilamentView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use TomatoPHP\FilamentTenancy\Models\Tenant;
use Throwable;
use function Filament\Support\is_app_url;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * @throws Throwable
     */
    protected function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation(collect($data)->except('domain')->toArray());
        $record->domains()->create(['domain' => collect($data)->get('domain')]);
        return $record;
    }

    public function create(bool $another = false): void
    {
        $this->authorizeAccess();

        $this->callHook('beforeValidate');

        $data = $this->form->getState();

        $this->callHook('afterValidate');

        $data = $this->mutateFormDataBeforeCreate($data);

        $this->callHook('beforeCreate');

        $this->record = $this->handleRecordCreation($data);

        $this->form->model($this->getRecord())->saveRelationships();

        $this->callHook('afterCreate');

        $this->rememberData();

        $this->getCreatedNotification()?->send();

        if ($another) {
            // Ensure that the form record is anonymized so that relationships aren't loaded.
            $this->form->model($this->getRecord()::class);
            $this->record = null;

            $this->fillForm();

            return;
        }

        $redirectUrl = $this->getRedirectUrl();

        $record = $this->record;

        try {
            if (!config('filament-tenancy.single_database')) {
                $dbName = config('tenancy.database.prefix') . $record->id . config('tenancy.database.suffix');
                config(['database.connections.dynamic.database' => $dbName]);
            }
            DB::purge('dynamic');

            DB::connection('dynamic')->getPdo();
        } catch (Exception $e) {
            throw new Exception("Failed to connect to tenant database: {$dbName}");
        }

        $data = [
            'name' => $record->name,
            'email' => $record->email,
            'password' => $record->password,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $user = DB::connection('dynamic')
            ->table('users')
            ->where('email', $record->email);


        if (config('filament-tenancy.single_database')) {
            $user = $user->where('tenant_id', $record->id);

            $data['tenant_id'] = $record->id;
        }

        $user->updateOrInsert(
            [
                'email' => $data['email'],
            ],
            $data,
        );

        $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode() && is_app_url($redirectUrl));
    }


    /**
     * @throws Throwable
     */
    private function createTenantRecord(array $data)
    {
        Log::info("Saving Tenant");
        $record = new Tenant(collect($data)->except('domain')->toArray());
        $record->saveOrFail();
        Log::info("Saving Domains");
        $record = $record::find($record->id);
        $record->domains()->create(['domain' => collect($data)->get('domain')]);
        Artisan::call('route:clear');
        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
