<?php

namespace SailCMS\Session;

use Carbon\Carbon;
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

final class Stateless implements AppSession
{
    private Builder $builder;
    private string $token = '';

    /**
     *
     * @throws Exception
     *
     */
    public function __construct()
    {
        $this->builder = new Builder(new JoseEncoder(), ChainedFormatter::default());
        $now = new \DateTimeImmutable();

        $mins = (setting('session.ttl', 21_600) / 60);

        $ttl = setting('session.ttl', 21_600);
        $tz = setting('timezone', 'America/New_York');
        $exp = Carbon::now($tz)->addSeconds($ttl)->toDateTimeImmutable();

        $this->builder
            ->issuedBy(setting('session.jwt.issuer', 'SailCMS'))
            ->identifiedBy(bin2hex(random_bytes(12)))
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($exp)
            ->permittedFor(setting('session.jwt.domain', 'localhost'));
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

        // Set cookie for token
        $expire = time() + setting('session.ttl', 21_600);
        $domain = setting('session.jwt.domain', 'localhost');
        $token = $this->builder->getToken($algo, $genkey)->toString();

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
        return Collection::init();
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
            setting('session.jtw.domain', 'localhost'),
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
            $token = $_COOKIE['sc_jwt'] ?? '';

            if (empty($token)) {
                $token = $this->token;
            }

            if (empty($token)) {
                return '';
            }

            $parser = new Parser(new JoseEncoder());
            $token = $parser->parse($token);

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
            $headers = getallheaders();
            $cookie = '';

            if (!empty($headers['X-Access-Token'])) {
                $cookie = $headers['X-Access-Token'];
            } elseif (!empty($headers['x-access-token'])) {
                $cookie = $headers['x-access-token'];
            }

            $this->token = $cookie;

            if ($cookie === 'null' || empty(trim($cookie))) {
                $this->token = '';
                $cookie = '';
            }
        }

        if (empty($cookie)) {
            return false;
        }

        if (substr_count($cookie, '.') !== 2) {
            return false;
        }

        $_ENV['JWT'] = $cookie;

        $parser = new Parser(new JoseEncoder());
        $token = $parser->parse($cookie);
        $validator = new Validator();

        $thekey = Filesystem::manager()->read('local://vault/.security_key');
        $genkey = InMemory::plainText($thekey);

        if (!$validator->validate($token, new IssuedBy(setting('session.jwt.issuer', 'SailCMS')))) {
            return false;
        }

        if (!$validator->validate($token, new SignedWith(new Sha256(), $genkey))) {
            return false;
        }

        if (!$validator->validate($token, new PermittedFor(setting('session.jwt.domain', 'localhost')))) {
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

