<?php

namespace Jigoshop\Api\Routes\V1\Product;

use Jigoshop\Admin\Migration\Exception;
use Jigoshop\Api\Contracts\ApiControllerContract;
use Jigoshop\Api\Routes\V1\BaseController;
use Jigoshop\Entity\Product as ProductEntity;
use Jigoshop\Entity\Product;
use Jigoshop\Entity\Product\Attachment as AttachmentEntity;
use Jigoshop\Service\ProductService;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class ProductAttachments
 * @package Jigoshop\Api\Routes\V1;
 * @author MAciej Maciaszek
 */
class ProductAttachments extends BaseController implements ApiControllerContract
{
    /** @var  App */
    protected $app;
    /** @var Product $product */
    protected $product;

    /**
     * product service is service we are using for product attachments
     * @var string
     */
    protected $serviceName = 'jigoshop.service.product';
    /**
     * @var string
     */
    protected $entityName = 'attachment';

    /**
     * Products constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->app = $app;
        $app->get('', array($this, 'findAll'));
        $app->get('/{id:[0-9]+}', array($this, 'findOne'));
        $app->put('/{id:[0-9]+}', array($this, 'update'));
        $app->delete('/{id:[0-9]+}', array($this, 'delete'));
        $app->post('', array($this, 'create'));
    }

    /**
     * get all attachments for product
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function findAll(Request $request, Response $response, $args)
    {
        $queryParams = $this->setDefaultQueryParams($request->getParams());

        $this->setProduct($args);
        $items = $this->getObjects($args);
        $itemsCount = $this->getObjectsCount();
        return $response->withJson([
            'success' => true,
            'all_results' => $itemsCount,
            'pagelen' => $queryParams['pagelen'],
            'page' => $queryParams['page'],
            'next' => '',
            'previous' => '',
            'data' => array_values($items),
        ]);
    }

    /**
     * get specified attachment for product
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function findOne(Request $request, Response $response, $args)
    {
        $this->setProduct($args);
        $attachment = $this->validateObjectFinding($args);
        if (!$this->product->hasAttachment($attachment->getId())) {
            throw new Exception("Product has not this attachment",404);
        }
        return $response->withJson([
            'success' => true,
            'data' => $attachment,
        ]);
    }

    /**
     * overrided create function from BaseController
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function create(Request $request, Response $response, $args)
    {
     /* todo implement this */
    }

    /**
     * overrided update function from BaseController
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function update(Request $request, Response $response, $args)
    {
        /* todo implement this */
    }

    /**
     * remove attribute from product
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function delete(Request $request, Response $response, $args)
    {
        /* todo implement this */
    }

    /**
     * setting product
     * @param $args
     */
    protected function setProduct($args)
    {
        // validating product first
        if (!isset($args['productId']) || empty($args['productId'])) {
            throw new Exception("Product Id was not provided",422);
        }
        $product = $this->service->find($args['productId']);
        if (!$product instanceof ProductEntity) {
            throw new Exception("Product not found.", 404);
        }
        $this->product = $product;
    }

    /**
     * @param array $args
     * @return ProductEntity\Attribute[]
     */
    protected function getObjects(array $args)
    {
        /** @var ProductService $service */
        $service = $this->service;
        return $service->getAttachments($this->product);
    }

    /**
     * @return int
     */
    protected function getObjectsCount()
    {
        /** @var ProductService $service */
        $service = $this->service;
        $items = $service->getAttachments($this->product);
        return count($items);
    }

    /**
     * find attachment and validate it's
     * @param $args
     * @return mixed
     */
    protected function validateObjectFinding($args)
    {
        if (!isset($args['id']) || empty($args['id'])) {
            throw new Exception("$this->entityName ID was not provided",422);
        }

        $object = $this->service->getAttachment($args['id']);
        $entity = self::JIGOSHOP_ENTITY_PREFIX . 'Product\\' . ucfirst($this->entityName);

        if (!$object instanceof $entity) {
            throw new Exception("$this->entityName not found.", 404);
        }

        return $object;
    }

}