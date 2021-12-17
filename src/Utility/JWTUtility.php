<?php

declare(strict_types=1);

namespace Dduers\F3App\Utility;

use Prefab;

final class JWTUtility extends Prefab
{
    private const DEFAULT_SECRET = 'öjhefwn/&()/&HIJFIOENFIOEN()*Zuio)(*';
    private const DEFAULT_LIFETIME = 60;
    static private array $_options = [];

    function __construct()
    {
    }

    /**
     * generate jwt token
     * @param array $headers_ array('alg'=>'HS256','typ'=>'JWT');
     * @param array $payload_ array('email'=>'John.Doe@nobody.com', 'exp'=>(time() + 60));
     * @param string $secret_
     * @return string token
     */
    static public function generate(array $payload_, array $headers_ = ['typ' => 'JWT', 'alg' => 'HS256'], string $secret_ = self::DEFAULT_SECRET): string
    {
        $_secret = (self::$_options['secret'] ?? '') ?: $secret_;
        $_payload = array_merge(
            $payload_,
            ['exp' => (self::$_options['lifetime'] ?? '') ? time() + (int)self::$_options['lifetime'] : time() + (int)self::DEFAULT_LIFETIME]
        );

        $_encoded_headers = self::base64url_encode(json_encode($headers_));
        $_encoded_payload = self::base64url_encode(json_encode($_payload));
        $_signature = hash_hmac('SHA256', implode('.', [$_encoded_headers, $_encoded_payload]), $_secret, true);
        $_encoded_signature = self::base64url_encode($_signature);
        $_jwt = implode('.', [$_encoded_headers, $_encoded_payload, $_encoded_signature]);
        return $_jwt;
    }

    /**
     * validate jwt token
     * @param string $jwt_
     * @param string $secret_
     * @return bool 
     */
    static public function validate(string $jwt_, string $secret_ = self::DEFAULT_SECRET): bool
    {
        $_secret = (self::$_options['secret'] ?? '') ?: $secret_;
        $_jwt_split = explode('.', $jwt_);

        $_decoded_header = base64_decode($_jwt_split[0]);
        $_decoded_payload = base64_decode($_jwt_split[1]);
        $_signature_given = $_jwt_split[2];

        // check the expiration time - note this will cause an error if there is no 'exp' claim in the jwt
        $_expiration = json_decode($_decoded_payload)->exp;
        $_is_token_expired = ($_expiration - time()) < 0;

        // build signature based on the header and payload using the secret
        $_encoded_header = self::base64url_encode($_decoded_header);
        $_encoded_payload = self::base64url_encode($_decoded_payload);
        $_signature = hash_hmac('SHA256', implode('.', [$_encoded_header, $_encoded_payload]), $_secret, true);
        $_encoded_signature = self::base64url_encode($_signature);

        // verify it matches the signature provided in the jwt
        $_is_signature_valid = ($_encoded_signature === $_signature_given);

        return ($_is_token_expired || !$_is_signature_valid) ? false : true;
    }

    /**
     * decode payload of jwt token
     * @param string $jwt_
     * @param string $secret_
     * @return NULL|array
     */
    static public function decode(string $jwt_, string $secret_ = self::DEFAULT_SECRET)
    {
        if (!self::validate($jwt_, $secret_))
            return NULL;

        $_jwt_split = explode('.', $jwt_);
        //$_decoded_header = base64_decode($_jwt_split[0]);
        $_decoded_payload = base64_decode($_jwt_split[1]);
        //$_signature_given = $_jwt_split[2];

        return json_decode($_decoded_payload, true);
    }

    /**
     * base64 url encoder
     * @param string $json_string_
     * @return string
     */
    static private function base64url_encode(string $json_string_): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($json_string_));
    }

    /**
     * set cookie options
     * @param array $options_
     * @return void
     */
    static public function setOptions(array $options_): void
    {
        self::$_options = array_filter($options_);
    }
}
