<?php
try {
    $req = new \Illuminate\Http\Request();
    $rand = rand(1000,9999);
    $req->replace([
        'nik' => '999'.$rand,
        'name' => 'AutoTest'.$rand,
        'phone' => '0812345',
        'address' => 'Test Addr',
        'email' => 'auto'.$rand.'@test.com',
        'criteria' => 'Booked',
        'cluster_id' => \App\Models\Cluster::first()->id,
        'payment_method' => 'Cash'
    ]);
    $req->setUserResolver(function(){ return \App\Models\User::first(); });
    $controller = new \App\Http\Controllers\CustomersController();
    $controller->store($req);

    $exists = \App\Models\Sale::where('status', 'Booked')
        ->whereHas('customer', function($q) use ($rand) {
            $q->where('name', 'AutoTest'.$rand);
        })->exists();

    echo $exists ? "VERIFIED_SUCCESS" : "VERIFIED_FAIL";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
