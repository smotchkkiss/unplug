# Unplug

A WordPress microframework

## Installation

Via composer:

```sh
composer require em4nl/unplug
```

## Usage

Unplug is a microframework for use in a WordPress theme when you
need more control over the frontend of your website than WordPress
gives you by default. It's highly recommended to use it together
with the [Twig](https://twig.symfony.com/Homepage) template engine.

The basic idea is to bypass WordPress' routing/template hierarchy
mechanisms completely and roll our own.

I assume you're using autoloading and your composer vendor dir is
at `./vendor`.

### functions.php

To make sure WordPress doesn't run its default query/template,
you'll need to add a call to `Em4nl\Unplug\unplug` in your theme's
`functions.php`.

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

Em4nl\Unplug\unplug();
```

### index.php

For most themes, this will probably be the only other PHP file
you'll need (especially if using Twig).

```php
<?php

// No need to require autoload again if you already did in
// `functions.php`, WordPress will always run that before
// `index.php`.

use Em4nl\Unplug;

// Basically, just use the _use, get and post functions to set up
// your frontend routes.

Unplug\_use(function(&$context) {
    // _use callbacks will be called on all routes. Use this to add
    // stuff to your context that you always need. For example a
    // global image for sharing your website on facebook and
    // twitter, loaded from an ACF field:
    $context['share_image'] = get_field('share_image', 'options');
});

Unplug\_use(function(&$context) {
    // You can have as many _use callbacks as you like. Another
    // example would be to add a menu
    $context['menu'] = array(/* TODO load this from WordPress */);
});

Unplug\_use(function($context) {
    // If you don't want to mutate the $context array directly, you
    // can also return a changed copy.
    // If you want to have the Twig template engine available on
    // every route, why not store it in the $context
    $twig_loader = new Twig_Loader_Filesystem(
        get_template_directory() . '/templates'
    );
    $context['twig'] = new Twig_Environment($twig_loader, [
        'debug' => true,
    ]);
    $context['twig']->addExtension(new Twig_Extension_Debug());
    return $context;
});

Unplug\get('/', function($context) {
    // The index route. Return a string to send html. Especially
    // useful in conjunction with Twig
    return $context['twig']->render('home.twig', $context);
});

Unplug\get('/hi-world', function($context) {
    // Or just echo your response
    echo "Hello world!";
});

Unplug\get('/api', function($context) {
    // Return an array to automatically send a json response
    return ['error' => NULL];
});

Unplug\get('/:param', function($context) {
    // Routes can have parameters; the parameter values are
    // collected into the $context['params'] array
    return "Hi, {$context['params'][0]}!";
});

Unplug\get('/menu/open?', function($context) {
    // Routes can have optional path segments. To find out wether
    // they're present, examine $context['path']
    return "You're visiting {$context['path']}";
});

Unplug\get('/test/*', function($context) {
    // Routes can also have wildcards that match any number of
    // segments. The content of the wildcard is also exposed in
    // $context['params']
    return "The wildcard path: {$context['params'][0]}";
});

Unplug\post('/form', function($context) {
    // You can also have POST routes. Unplug doesn't take care of
    // the posted data for you; just use the $_POST array for that
    // (Same goes for query parameters in $_GET)
});

function my_404($context) {
    // If you want to return something other than a '200 OK' (or a
    // 200 _explicitly_), you can use the `Unplug\ok`,
    // `Unplug\not_found`, `Unplug\moved_permanently` and
    // `Unplug\found` functions.
    return Unplug\not_found(
        $context['twig']->render('error_404.twig', $context)
    );
}

Unplug\get('/post/:title', function($context) {
    // Sometimes you have a parametrised route where not all
    // parameter values are valid. This can be handled e.g. like
    // this
    $context['post'] = my_get_post($context['params'][0]);
    if ($context['post']) {
        return $context['twig']->render('post.twig', $context);
    } else {
        return my_404($context);
    }
});

// You'll almost always want to register a global catchall callback
// that will be used if no other route matches where you deliver
// your 404 page.
Unplug\catchall('my_404');

// After all routes are set up, you still have to call this, or
// nothing will be run!
Unplug\dispatch();
```

### index.php (WordPress root) (**CACHING!**)

While you can just use Unplug's caching from inside your theme, the
most efficient way is to circumvent WordPress completely and only
load it when we can't or don't want to serve a page from cache. In
order to do so, you'll need to replace WP's root `index.php` file
with your own.

Rename `index.php`---the one at the root of your WordPress
installation, **not the one in your theme**, to something else,
like `wp-index.php`.

Then create a new `index.php` file in its place and put the
following code in it:

```php
<?php

require_once __DIR__ . '/wp-content/themes/<name-of-your-theme>/vendor/autoload.php';

Em4nl\Unplug\front_controller([
    'cache_dir' => __DIR__ . '/_unplug_cache',
    'wp_index_php' => __DIR__ . '/wp-index.php',
]);
```

This will serve files from the cache and only load WordPress if a
file isn't found or invalidated by a custom function given through
the optional `invalidate` parameter.

If you need to disable the cache (temporarily) you'll have to
switch out the `index.php` again.

```sh
mv index.php unplug-index.php
mv wp-index.php index.php
```

## Development

Install dependencies

```sh
composer install
```

Run tests

```sh
./vendor/bin/phpunit tests
```

## License

[The MIT License](https://github.com/em4nl/unplug/blob/master/LICENSE)
