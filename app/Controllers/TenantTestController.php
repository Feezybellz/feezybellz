<?php

namespace App\Controllers;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use App\Models\User;

class TenantTestController
{
    public function index(Request $request, Response $response)
    {
        $users = User::all();
        $activeTenant = session()->get('active_tenant', 'Main Database (Default)');
        
        $html = "<h1>Multi-Tenant Connection Test</h1>";
        $html .= "<p>Current Active Tenant: <strong>{$activeTenant}</strong></p>";
        
        $html .= "<h3>Switch Tenant:</h3>";
        $html .= "<ul>";
        $html .= "<li><a href='?switch_tenant='>Default (Main)</a></li>";
        $html .= "<li><a href='?switch_tenant=tenant1'>Tenant 1</a></li>";
        $html .= "<li><a href='?switch_tenant=tenant2'>Tenant 2</a></li>";
        $html .= "<li><a href='?switch_tenant=tenant3'>Tenant 3</a></li>";
        $html .= "</ul>";

        $html .= "<h3>Users in current DB:</h3>";
        $html .= "<ul>";
        if (empty($users)) {
            $html .= "<li>No users found.</li>";
        } else {
            foreach ($users as $user) {
                $html .= "<li>ID: {$user->id} | Name: {$user->name}</li>";
            }
        }
        $html .= "</ul>";

        return $response->html($html);
    }
}
