<?php
try {
    $req = new \Illuminate\Http\Request();
    $rand = rand(1000,9999);
    $cluster = \App\Models\Cluster::first();
    $price = $cluster->price;
    $dp = 10000000;
    $cicilan = 10;
    
    $req->replace([
        'nik' => '777'.$rand,
        'name' => 'CashTest'.$rand,
        'phone' => '0812345',
        'address' => 'Test Addr',
        'email' => 'cash'.$rand.'@test.com',
        'criteria' => 'Booked',
        'cluster_id' => $cluster->id,
        'payment_method' => 'cash_bertahap',
        'payment_amount' => $dp,
        'cicilan' => $cicilan
    ]);
    $req->setUserResolver(function(){ return \App\Models\User::first(); });
    $controller = new \App\Http\Controllers\CustomersController();
    $controller->store($req);

    $customer = \App\Models\Customer::where('name', 'CashTest'.$rand)->first();
    $sale = \App\Models\Sale::where('customer_id', $customer->id)->first();
    
    $expected = ($price - $dp) / $cicilan;
    
    if (abs($sale->monthly_installment - $expected) < 1) {
        file_put_contents('verification_result.txt', "VERIFIED_SUCCESS: " . $sale->monthly_installment);
    } else {
        file_put_contents('verification_result.txt', "VERIFIED_FAIL: Expected $expected, Got " . $sale->monthly_installment);
    }
} catch (\Exception $e) {
    file_put_contents('verification_result.txt', "ERROR: " . $e->getMessage());
}
