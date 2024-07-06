<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Psr\Container\ContainerInterface;
use RuntimeException;
use Ufo\JsonRpcBundle\ConfigService\RpcDocsConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\JsonRpcBundle\Package;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcObject\RpcTransport;

use function is_null;

class ServiceLocator implements ContainerInterface
{
    const ENV_JSON_RPC_2 = 'JSON-RPC-2.0';
    const JSON = 'application/json';
    const POST = 'POST';

    protected string $contentType = self::JSON;

    protected ?string $description = null;

    protected string $envelope;

    protected array $services = [];

    protected string $target;

    protected string $methodsKey = RpcDocsConfig::DEFAULT_KEY_FOR_METHODS;

    public function __construct(
        protected RpcMainConfig $mainConfig
    ) {
        $this->methodsKey = $this->mainConfig->docsConfig->keyForMethods;
    }

    /**
     * Get transport.
     *
     * @return array[]
     */
    public function getTransport(): array
    {
        $http = RpcTransport::fromArray($this->mainConfig->url);
        $transport = [
            'sync' => $http->toArray(),
        ];
        $transport['sync'] += [
            'method' => self::POST,
        ];
        if ($this->mainConfig->docsConfig->asyncDsnInfo && !is_null($this->mainConfig->asyncConfig->rpcAsync)) {
            $async = RpcTransport::fromDsn($this->mainConfig->asyncConfig->rpcAsync);
            $transport['async'] = $async->toArray();
        }

        return $transport;
    }

    /**
     * Retrieve envelope.
     *
     * @return string
     */
    public function getEnvelope(): string
    {
        if (!isset($this->envelope)) {
            $this->envelope = self::ENV_JSON_RPC_2.'/UFO-RPC-' . Package::version();
        }
        return $this->envelope;
    }

    /**
     * Retrieve content type
     *
     * Content-Type of response; default to application/json.
     *
     * @return string
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setTarget(string $target): static
    {
        $this->target = $target;

        return $this;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description ?? $this->getMainConfig()->projectDesc;
    }

    /**
     * @param Service $service
     * @return $this
     */
    public function addService(Service $service): static
    {
        $name = $service->getName();
        if (array_key_exists($name, $this->services)) {
            throw new RuntimeException('Attempt to register a service already registered detected');
        }
        $this->services[$name] = $service;

        return $this;
    }

    /**
     * @param Service[] $services
     * @return $this
     */
    public function addServices(array $services): static
    {
        foreach ($services as $service) {
            $this->addService($service);
        }

        return $this;
    }

    /**
     * @param string $id
     * @return Service
     * @throws ServiceNotFoundException
     */
    public function get(string $id): Service
    {
        if (!$this->has($id)) {
            throw new ServiceNotFoundException('Service "'.$id.'" is not found on RPC Service Locator');
        }

        return $this->services[$id];
    }

    public function count(): int
    {
        return count($this->services);
    }

    public function empty(): bool
    {
        return empty($this->services);
    }

    public function has(string $id): bool
    {
        if (!array_key_exists($id, $this->services)) {
            return false;
        }

        return true;
    }

    /**
     * @return Service[]
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * @throws RpcMethodNotFoundExceptionRpc
     */
    public function getService(string $name): Service
    {
        if (isset($this->services[$name])) {
            return $this->services[$name];
        }
        throw new RpcMethodNotFoundExceptionRpc("Method '$name' is not found");
    }

    public function removeService(string $name): bool
    {
        if (!array_key_exists($name, $this->services)) {
            return false;
        }
        unset($this->services[$name]);

        return true;
    }

    /**
     * Cast to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $service = [
            'WARNING'    => 'This documentation format is deprecated and will be removed soon. In version 7.0, it will be replaced with a format compliant with the OpenRPC specification (https://spec.open-rpc.org/).',
            'NEW_FORMAT' => (string)RpcTransport::fromArray($this->mainConfig->url) . '/openrpc.json',
            'envelope'    => $this->getEnvelope(),
            'name' => $this->getMainConfig()->projectName,
            'description' => $this->getDescription(),
            'contentType' => $this->contentType,
            'transport'   => $this->getTransport(),
        ];
        $services = $this->getServices();
        if (empty($services)) {
            return $service;
        }
        $service[$this->methodsKey] = [];
        foreach ($services as $name => $svc) {
            $service[$this->methodsKey][$name] = $svc->toArray();
        }
        $service['readDocs'] = [
            'json-rpc' => Package::protocolSpecification(),
            Package::bundleName() => Package::bundleDocumentation(),
        ];
        return $service;
    }

    public function getMainConfig(): RpcMainConfig
    {
        return $this->mainConfig;
    }
}

