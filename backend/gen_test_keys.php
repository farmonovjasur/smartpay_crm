<?php
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($key, $privKey);
$details = openssl_pkey_get_details($key);
file_put_contents('config/jwt/private-test.pem', $privKey);
file_put_contents('config/jwt/public-test.pem', $details['key']);
echo "Keys generated\n";
