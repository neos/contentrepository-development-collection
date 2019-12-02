<?php

namespace Neos\EventSourcedContentRepository\Standalone\Configuration;

final class DatabaseConnectionParams
{
    /**
     * @var array
     */
    private $params;

    private function __construct(array $params)
    {
        $this->params = $params;
    }


    static function create(): self
    {
        return new self([
            'driver' => 'pdo_mysql'
        ]);
    }

    public function mysqlDriver(): self
    {
        $params = $this->params;
        $params['driver'] = 'pdo_mysql';
        return new DatabaseConnectionParams($params);
    }

    public function user(string $user): self
    {
        $params = $this->params;
        $params['user'] = $user;
        return new DatabaseConnectionParams($params);
    }

    public function password(string $password): self
    {
        $params = $this->params;
        $params['password'] = $password;
        return new DatabaseConnectionParams($params);
    }

    public function host(string $host): self
    {
        $params = $this->params;
        $params['host'] = $host;
        return new DatabaseConnectionParams($params);
    }

    public function dbname(string $dbname): self
    {
        $params = $this->params;
        $params['dbname'] = $dbname;
        return new DatabaseConnectionParams($params);
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
