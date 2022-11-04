<?php

namespace SailCMS\Session;

use Exception;
use League\Flysystem\FilesystemException;
use SailCMS\Collection;
use SailCMS\Contracts\AppSession;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Token\Builder;
use SailCMS\Filesystem;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\RelatedTo;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Validator;

class Stateless implements AppSession
{
    private Builder $builder;

    /**
     *
     * @throws Exception
     *
     */
    public function __construct()
    {
        $this->builder = new Builder(new JoseEncoder(), ChainedFormatter::default());
        $now = new \DateTimeImmutable();

        $mins = ($_ENV['SETTINGS']->get('session.ttl') / 60);

        $this->builder
            ->issuedBy($_ENV['SETTINGS']->get('session.jwt.issuer'))
            ->identifiedBy(bin2hex(random_bytes(12)))
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify('+ ' . $mins . ' minutes'))
            ->permittedFor($_ENV['SETTINGS']->get('session.jwt.domain'));
    }

    /**
     *
     * Set the 'user_id' value for the jwt
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     *
     */
    public function set(string $key, mixed $value): void
    {
        if ($key !== 'user_id') {
            throw new \RuntimeException('Key name is required to be user_id', 0400);
        }

        $this->builder->relatedTo($value);
    }

    /**
     *
     * Unimplemented
     *
     * @param  string  $key
     * @return void
     *
     */
    public function remove(string $key): void
    {
        // Not implemented
    }

    /**
     *
     * Get the Token value
     *
     * @param  string  $key
     * @return string
     * @throws Exception
     * @throws FilesystemException
     *
     */
    public function get(string $key): string
    {
        // Key is not important
        $algo = new Sha256();
        $thekey = Filesystem::manager()->read('local://vault/.security_key');
        $genkey = InMemory::plainText($thekey);

        $token = $this->builder->getToken($algo, $genkey)->toString();

        // Set cookie for token
        $expire = time() + $_ENV['SETTINGS']->get('session.ttl');
        $domain = $_ENV['SETTINGS']->get('session.jwt.domain');

        setcookie('sc_jwt', $token, [
            'expires' => $expire,
            'path' => '/',
            'domain' => $domain,
            'secure' => true,
            'httponly' => true
        ]);

        return $token;
    }

    /**
     *
     * Unimplemented
     *
     * @return Collection
     *
     */
    public function all(): Collection
    {
        return new Collection([]);
    }

    /**
     *
     * Clear the jwt token cookie
     *
     * @return void
     *
     */
    public function clear(): void
    {
        // Expire the cookie
        setcookie(
            'sc_jwt',
            '',
            time() - 1,
            '/',
            $_ENV['SETTINGS']->get('session.jtw.domain'),
            true,
            true
        );
    }

    /**
     *
     * Get User Id
     *
     * @return string
     * @throws FilesystemException
     *
     */
    public function getId(): string
    {
        $isValid = $this->validate();

        if ($isValid) {
            $cookie = $_COOKIE['sc_jwt'] ?? '';

            if (empty($cookie)) {
                return '';
            }

            $parser = new Parser(new JoseEncoder());
            $token = $parser->parse($cookie);

            return $token->claims()->get('sub') ?? '';
        }

        return '';
    }

    /**
     *
     * Validate the token
     *
     * @return bool
     * @throws FilesystemException
     *
     */
    private function validate(): bool
    {
        $cookie = $_COOKIE['sc_jwt'] ?? '';

        // Cookie is empty, check header instead
        if (empty($cookie)) {
            $cookie = getallheaders()['x-access-token'] ?? '';
        }

        if (empty($cookie)) {
            return false;
        }

        $parser = new Parser(new JoseEncoder());
        $token = $parser->parse($cookie);
        $validator = new Validator();

        $thekey = Filesystem::manager()->read('local://vault/.security_key');
        $genkey = InMemory::plainText($thekey);

        if (!$validator->validate($token, new IssuedBy($_ENV['SETTINGS']->get('session.jwt.issuer')))) {
            return false;
        }

        if (!$validator->validate($token, new SignedWith(new Sha256(), $genkey))) {
            return false;
        }

        if (!$validator->validate($token, new PermittedFor($_ENV['SETTINGS']->get('session.jwt.domain')))) {
            return false;
        }

        return true;
    }

    /**
     *
     * Return type of session
     *
     * @return string
     *
     */
    public function type(): string
    {
        return 'stateless';
    }
}

