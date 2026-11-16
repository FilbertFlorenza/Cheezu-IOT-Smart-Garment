<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

class IssueServiceToken extends Command
{
    protected $signature = 'service:token
        {service : Service account name or email}
        {--ability=* : Ability to grant (repeatable)}
        {--name=service-token : Token label}
        {--keep-existing : Keep previously issued tokens active}';

    protected $description = 'Issue a Sanctum API token for an internal service account';

    public function handle(): int
    {
        $needle = (string) $this->argument('service');

        $user = User::query()
            ->where('role', Role::Service)
            ->where(fn ($query) => $query->where('email', $needle)->orWhere('name', $needle))
            ->first();

        if ($user === null) {
            $this->error("No SERVICE account matches '{$needle}'.");
            $this->line('Available service accounts:');

            User::query()->where('role', Role::Service)->get(['name', 'email'])
                ->each(fn (User $service) => $this->line(" - {$service->name} <{$service->email}>"));

            return self::FAILURE;
        }

        $abilities = array_values(array_unique((array) $this->option('ability')));

        if ($abilities === []) {
            $this->error('Provide at least one --ability option.');

            return self::FAILURE;
        }

        if (! $this->option('keep-existing')) {
            $user->tokens()->delete();
        }

        $token = $user->createToken((string) $this->option('name'), $abilities);

        $this->newLine();
        $this->info('Token issued for '.$user->name.' ('.$user->email.').');
        $this->line('Abilities: '.implode(', ', $abilities));
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->comment('Store this token securely. It is shown only once.');

        return self::SUCCESS;
    }
}
