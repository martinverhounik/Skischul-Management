<?php

namespace App\Console\Commands;

use App\Actions\Schools\CreateSchoolDefaults;
use App\Actions\Users\InviteUser;
use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Sets up a new ski school and invites its first owner.
 * This is how a school gets onboarded until a super-admin area exists.
 *
 *   php artisan school:create "Skischule Muster" anna@example.com "Anna Muster"
 */
class CreateSchool extends Command
{
    protected $signature = 'school:create
        {name : Name of the ski school}
        {email : Email address of the first owner}
        {owner : Full name of the first owner}
        {--locale=de : Default language (de or en)}
        {--timezone=Europe/Vienna : Time zone of the school}';

    protected $description = 'Create a ski school and email an invitation to its first owner';

    public function handle(InviteUser $inviteUser, CreateSchoolDefaults $defaults): int
    {
        $validator = Validator::make([
            'email' => $this->argument('email'),
            'locale' => $this->option('locale'),
            'timezone' => $this->option('timezone'),
        ], [
            'email' => ['required', 'email', 'unique:users,email'],
            'locale' => ['in:de,en'],
            'timezone' => ['timezone'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $name = $this->argument('name');
        $slug = Str::slug($name);
        if (School::where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        $school = School::create([
            'name' => $name,
            'slug' => $slug,
            'locale' => $this->option('locale'),
            'timezone' => $this->option('timezone'),
            'week_starts_on' => 0,
        ]);

        $defaults->handle($school);

        /** @var User $owner */
        $owner = $inviteUser->handle($school, $this->argument('owner'), $this->argument('email'), Role::Owner);

        $this->info("School \"{$school->name}\" created. Invitation sent to {$owner->email}.");

        return self::SUCCESS;
    }
}
