<?php

namespace Fleetbase\Http\Controllers\Internal\v1;

use Fleetbase\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstallerController extends Controller
{
    /**
     * Checks to see if this is the first time Fleetbase is being used by checking if any organizations exists.
     *
     * @return \Illuminate\Http\Response
     */
    public function initialize()
    {
        $shouldInstall = false;
        $shouldOnboard = false;

        try {
            DB::connection()->getPdo();
            if (!DB::connection()->getDatabaseName()) {
                $shouldInstall = true;
            } else {
                if (Schema::hasTable('companies')) {
                    if (DB::table('companies')->count() == 0) {
                        $shouldOnboard = true;
                    }
                } else {
                    $shouldInstall = true;
                }
            }
        } catch (\Exception $e) {
            $shouldInstall = true;
        }

        return response()->json(
            [
                'shouldInstall' => $shouldInstall,
                'shouldOnboard' => $shouldOnboard
            ]
        );
    }

    public function createDatabase()
    {
        Artisan::call('mysql:createdb');

        return response()->json(
            [
                'status' => 'success'
            ]
        );
    }

    public function migrate()
    {
        shell_exec(base_path('artisan') . ' migrate');
        Artisan::call('sandbox:migrate');

        return response()->json(
            [
                'status' => 'success'
            ]
        );
    }

    public function seed()
    {
        Artisan::call('fleetbase:seed');

        return response()->json(
            [
                'status' => 'success'
            ]
        );
    }
}