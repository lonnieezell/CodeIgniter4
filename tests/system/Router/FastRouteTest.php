<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace CodeIgniter\Router;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * @internal
 *
 * @group Others
 */
final class FastRouteTest extends CIUnitTestCase
{
    private function getCollection(): RouteCollectionInterface
    {
        $routes = Services::routes();
        $routes->setDefaultNamespace('App\Controllers');
        $routes->setDefaultController('Home');
        $routes->setDefaultMethod('index');

        return $routes;
    }

    public function testMatchesStaticRoute(): void
    {
        $collection = $this->getCollection();
        $collection->get('/home', 'Home::index');
        $collection->get('/about', 'About::index');

        $fastRoute = new FastRoute($collection);

        $result = $fastRoute->match('/home', 'GET');

        $this->assertIsArray($result);
        $this->assertSame('\App\Controllers\Home::index', $result['handler']);
        $this->assertSame([], $result['params']);
    }

    public function testMatchesDynamicRouteWithNumPlaceholder(): void
    {
        $collection = $this->getCollection();
        $collection->get('/user/(:num)', 'User::show/$1');

        $fastRoute = new FastRoute($collection);

        $result = $fastRoute->match('/user/123', 'GET');

        $this->assertIsArray($result);
        $this->assertSame('\App\Controllers\User::show/$1', $result['handler']);
        $this->assertSame(['123'], $result['params']);
    }

    public function testMatchesDynamicRouteWithSegmentPlaceholder(): void
    {
        $collection = $this->getCollection();
        $collection->get('/post/(:segment)', 'Post::show/$1');

        $fastRoute = new FastRoute($collection);

        $result = $fastRoute->match('/post/hello-world', 'GET');

        $this->assertSame('\App\Controllers\Post::show/$1', $result['handler']);
        $this->assertSame(['hello-world'], $result['params']);
    }

    public function testMatchesDynamicRouteWithMultipleParameters(): void
    {
        $collection = $this->getCollection();
        $collection->get('/blog/(:segment)/(:num)', 'Blog::show/$1/$2');

        $fastRoute = new FastRoute($collection);

        $result = $fastRoute->match('/blog/my-post/42', 'GET');

        $this->assertSame('\App\Controllers\Blog::show/$1/$2', $result['handler']);
        $this->assertSame(['my-post', '42'], $result['params']);
    }

    public function testHandlesAnyPlaceholder(): void
    {
        $collection = $this->getCollection();
        $collection->get('/files/(:any)', 'Files::show/$1');

        $fastRoute = new FastRoute($collection);

        $result = $fastRoute->match('/files/some-file.txt', 'GET');

        $this->assertSame('\App\Controllers\Files::show/$1', $result['handler']);
        $this->assertSame(['some-file.txt'], $result['params']);
    }

    public function testHandlesManyRoutesWithChunking(): void
    {
        $collection = $this->getCollection();

        // Create 25 dynamic routes
        for ($i = 0; $i < 25; $i++) {
            $collection->get("/route{$i}/(:num)", "Controller{$i}::method/\$1");
        }

        $fastRoute = new FastRoute($collection, 10);

        // Should match route in first chunk
        $result1 = $fastRoute->match('/route5/42', 'GET');
        $this->assertSame('\App\Controllers\Controller5::method/$1', $result1['handler']);

        // Should match route in last chunk
        $result2 = $fastRoute->match('/route24/99', 'GET');
        $this->assertSame('\App\Controllers\Controller24::method/$1', $result2['handler']);
    }

    public function testPrefersStaticRouteOverDynamic(): void
    {
        $collection = $this->getCollection();
        $collection->get('/user/profile', 'User::profile');
        $collection->get('/user/(:segment)', 'User::show/$1');

        $fastRoute = new FastRoute($collection);

        $result = $fastRoute->match('/user/profile', 'GET');

        // Should match static route, not dynamic
        $this->assertSame('\App\Controllers\User::profile', $result['handler']);
        $this->assertSame([], $result['params']);
    }

    public function testHandlesRootRoute(): void
    {
        $collection = $this->getCollection();
        $collection->get('/', 'Home::index');

        $fastRoute = new FastRoute($collection);

        $result = $fastRoute->match('/', 'GET');

        $this->assertSame('\App\Controllers\Home::index', $result['handler']);
    }

    public function testReturnsNullForNonExistentRoute(): void
    {
        $collection = $this->getCollection();
        $collection->get('/home', 'Home::index');

        $fastRoute = new FastRoute($collection);

        $result = $fastRoute->match('/missing', 'GET');

        $this->assertNull($result);
    }

    public function testReturnsNullForWrongHTTPMethod(): void
    {
        $collection = $this->getCollection();
        $collection->get('/users', 'Users::index');

        $fastRoute = new FastRoute($collection);

        $result = $fastRoute->match('/users', 'POST');

        $this->assertNull($result);
    }

    public function testDistinguishesDifferentHTTPMethods(): void
    {
        $collection = $this->getCollection();
        $collection->get('/users', 'Users::index');
        $collection->post('/users', 'Users::create');

        $fastRoute = new FastRoute($collection);

        $resultGet  = $fastRoute->match('/users', 'GET');
        $resultPost = $fastRoute->match('/users', 'POST');

        $this->assertSame('\App\Controllers\Users::index', $resultGet['handler']);
        $this->assertSame('\App\Controllers\Users::create', $resultPost['handler']);
    }

    public function testHandlesAlphaPlaceholder(): void
    {
        $collection = $this->getCollection();
        $collection->get('/category/(:alpha)', 'Category::show/$1');

        $fastRoute = new FastRoute($collection);

        $result = $fastRoute->match('/category/books', 'GET');

        $this->assertSame('\App\Controllers\Category::show/$1', $result['handler']);
        $this->assertSame(['books'], $result['params']);
    }

    public function testHandlesAlphanumPlaceholder(): void
    {
        $collection = $this->getCollection();
        $collection->get('/product/(:alphanum)', 'Product::show/$1');

        $fastRoute = new FastRoute($collection);

        $result = $fastRoute->match('/product/abc123', 'GET');

        $this->assertSame('\App\Controllers\Product::show/$1', $result['handler']);
        $this->assertSame(['abc123'], $result['params']);
    }

    public function testHandlesHashPlaceholder(): void
    {
        $collection = $this->getCollection();
        $collection->get('/hash/(:hash)', 'Hash::show/$1');

        $fastRoute = new FastRoute($collection);

        $result = $fastRoute->match('/hash/a1b2c3d4', 'GET');

        $this->assertSame('\App\Controllers\Hash::show/$1', $result['handler']);
        $this->assertSame(['a1b2c3d4'], $result['params']);
    }

    public function testMatchesWithTrailingSlash(): void
    {
        $collection = $this->getCollection();
        $collection->get('/about', 'About::index');

        $fastRoute = new FastRoute($collection);

        // URI with trailing slash should be normalized
        $result = $fastRoute->match('/about/', 'GET');

        $this->assertSame('\App\Controllers\About::index', $result['handler']);
    }

    public function testMatchesWithLeadingSlash(): void
    {
        $collection = $this->getCollection();
        $collection->get('/contact', 'Contact::index');

        $fastRoute = new FastRoute($collection);

        $result = $fastRoute->match('contact', 'GET');

        $this->assertSame('\App\Controllers\Contact::index', $result['handler']);
    }

    public function testEmptyRouteCollection(): void
    {
        $collection = $this->getCollection();
        // No routes added

        $fastRoute = new FastRoute($collection);

        $result = $fastRoute->match('/anything', 'GET');

        $this->assertNull($result);
    }

    public function testCustomChunkSize(): void
    {
        $collection = $this->getCollection();

        // Add 15 dynamic routes
        for ($i = 0; $i < 15; $i++) {
            $collection->get("/item{$i}/(:num)", "Item{$i}::show/\$1");
        }

        // Use chunk size of 5
        $fastRoute = new FastRoute($collection, 5);

        // Should still match all routes
        $result1 = $fastRoute->match('/item0/1', 'GET');
        $this->assertSame('\App\Controllers\Item0::show/$1', $result1['handler']);

        $result2 = $fastRoute->match('/item14/99', 'GET');
        $this->assertSame('\App\Controllers\Item14::show/$1', $result2['handler']);
    }

    public function testMixedStaticAndDynamicRoutes(): void
    {
        $collection = $this->getCollection();
        $collection->get('/home', 'Home::index');
        $collection->get('/about', 'About::index');
        $collection->get('/user/(:num)', 'User::show/$1');
        $collection->get('/post/(:segment)', 'Post::show/$1');
        $collection->get('/contact', 'Contact::index');

        $fastRoute = new FastRoute($collection);

        // Test static routes
        $result1 = $fastRoute->match('/home', 'GET');
        $this->assertSame('\App\Controllers\Home::index', $result1['handler']);

        // Test dynamic routes
        $result2 = $fastRoute->match('/user/42', 'GET');
        $this->assertSame('\App\Controllers\User::show/$1', $result2['handler']);
        $this->assertSame(['42'], $result2['params']);
    }

    public function testReturnsOriginalRoutePattern(): void
    {
        $collection = $this->getCollection();
        $collection->get('/blog/(:segment)/(:num)', 'Blog::show/$1/$2');

        $fastRoute = new FastRoute($collection);

        $result = $fastRoute->match('/blog/my-post/42', 'GET');

        // The 'route' key should contain the original pattern
        $this->assertArrayHasKey('route', $result);
        $this->assertIsString($result['route']);
    }

    public function testHandlesAllHTTPVerbs(): void
    {
        $collection = $this->getCollection();
        $collection->get('/resource', 'Resource::index');
        $collection->post('/resource', 'Resource::create');
        $collection->put('/resource/(:num)', 'Resource::update/$1');
        $collection->patch('/resource/(:num)', 'Resource::patch/$1');
        $collection->delete('/resource/(:num)', 'Resource::delete/$1');
        $collection->options('/resource', 'Resource::options');

        $fastRoute = new FastRoute($collection);

        $this->assertSame('\App\Controllers\Resource::index', $fastRoute->match('/resource', 'GET')['handler']);
        $this->assertSame('\App\Controllers\Resource::create', $fastRoute->match('/resource', 'POST')['handler']);
        $this->assertSame('\App\Controllers\Resource::update/$1', $fastRoute->match('/resource/1', 'PUT')['handler']);
        $this->assertSame('\App\Controllers\Resource::patch/$1', $fastRoute->match('/resource/1', 'PATCH')['handler']);
        $this->assertSame('\App\Controllers\Resource::delete/$1', $fastRoute->match('/resource/1', 'DELETE')['handler']);
        $this->assertSame('\App\Controllers\Resource::options', $fastRoute->match('/resource', 'OPTIONS')['handler']);
    }
}
