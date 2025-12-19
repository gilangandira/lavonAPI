<?php
$sale = \App\Models\Sale::latest()->first();
file_put_contents('latest_sale.json', json_encode($sale, JSON_PRETTY_PRINT));
echo "Dumped to latest_sale.json";
