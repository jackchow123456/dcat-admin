<?php

namespace Dcat\Admin\Actions;

use Dcat\Admin\Admin;
use Dcat\Admin\Support\Helper;
use Dcat\Admin\Traits\HasHtmlAttributes;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

/**
 * Class Action.
 *
 * @method string href
 */
abstract class Action implements Renderable
{
    use HasHtmlAttributes, HasActionHandler;

    /**
     * @var array
     */
    protected static $selectors = [];

    /**
     * @var array|string
     */
    protected $primaryKey;

    public $interactor;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $selector;

    /**
     * @var string
     */
    public $selectorPrefix = '.admin-action-';

    /**
     * @var string
     */
    protected $method = 'POST';

    /**
     * @var string
     */
    public $event = 'click';

    /**
     * @var bool
     */
    protected $disabled = false;

    /**
     * @var bool
     */
    protected $usingHandler = true;

    /**
     * @var array
     */
    protected $htmlClasses = [];

    /**
     * Action constructor.
     *
     * @param string $title
     */
    public function __construct($title = null)
    {
        if ($title) {
            $this->title = $title;
        }
        $this->initInteractor();
    }

    /**
     * @throws \Exception
     */
    protected function initInteractor()
    {
        if ($hasForm = method_exists($this, 'form')) {
            $this->interactor = new Interactor\Form($this);
        }

        if ($hasDialog = method_exists($this, 'dialog')) {
//            $this->interactor = new Interactor\Dialog($this);
        }

        if ($hasForm && $hasDialog) {
            throw new \Exception('Can only define one of the methods in `form` and `dialog`');
        }
    }

    /**
     * 是否禁用动作.
     *
     * @param bool $disable
     *
     * @return $this
     */
    public function disable(bool $disable = true)
    {
        $this->disabled = $disable;

        return $this;
    }

    /**
     * @return bool
     */
    public function allowed()
    {
        return ! $this->disabled;
    }

    /**
     * Get primary key value of action.
     *
     * @return array|string
     */
    public function getKey()
    {
        return $this->primaryKey;
    }

    /**
     * 设置主键.
     *
     * @param mixed $key
     *
     * @return void
     */
    public function setKey($key)
    {
        $this->primaryKey = $key;
    }

    /**
     * @return string
     */
    protected function getElementClass()
    {
        return ltrim($this->selector(), '.');
    }

    /**
     * 获取动作标题.
     *
     * @return string
     */
    public function title()
    {
        return $this->title;
    }

    /**
     * @return mixed|string
     */
    public function selector()
    {
        if (is_null($this->selector)) {
            return $this->makeSelector($this->selectorPrefix);
        }

        return $this->selector;
    }

    /**
     * @param string $prefix
     * @param string $class
     *
     * @return string
     */
    public function makeSelector($prefix, $class = null)
    {
        $class = $class ?: static::class;

        if (! isset(static::$selectors[$class])) {
            static::$selectors[$class] = uniqid($prefix);
        }

        return static::$selectors[$class];
    }

    /**
     * @param string|array $class
     *
     * @return $this
     */
    public function addHtmlClass($class)
    {
        $this->htmlClasses = array_merge($this->htmlClasses, (array) $class);

        return $this;
    }

    /**
     * 需要执行的JS代码.
     *
     * @return string|void
     */
    protected function script()
    {
    }

    /**
     * @return string
     */
    protected function html()
    {
        $this->defaultHtmlAttribute('href', 'javascript:void(0)');

        return <<<HTML
<a {$this->formatHtmlAttributes()}>{$this->title()}</a>
HTML;
    }

    /**
     * @return void
     */
    protected function setupHandler()
    {
        if (
            ! $this->usingHandler
            || ! method_exists($this, 'handle')
        ) {
            return;
        }

        $this->addHandlerScript();
    }

    /**
     * @return string
     */
    public function render()
    {
        if (! $this->allowed()) {
            return '';
        }

        $this->setupHandler();

        $this->setupHtmlAttributes();

        if ($script = $this->script()) {
            Admin::script($script);
        }

        return $this->html();
    }

    /**
     * @return string
     */
    protected function formatHtmlClasses()
    {
        return implode(' ', array_unique($this->htmlClasses));
    }

    /**
     * @return void
     */
    protected function setupHtmlAttributes()
    {
        $this->addHtmlClass($this->getElementClass());

        $attributes = [
            'class' => $this->formatHtmlClasses(),
        ];

        if (method_exists($this, 'href') && ($href = $this->href())) {
            $this->usingHandler = false;

            $attributes['href'] = $href;
        }

        $this->defaultHtmlAttribute('style', 'cursor: pointer');
        $this->setHtmlAttribute($attributes);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return Helper::render($this->render());
    }

    /**
     * @param mixed ...$params
     *
     * @return $this
     */
    public static function make(...$params)
    {
        return new static(...$params);
    }


    /**
     * @param string $method
     * @param array $arguments
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function __call($method, $arguments = [])
    {
        if (in_array($method, Interactor\Interactor::$elements)) {
            return $this->interactor->{$method}(...$arguments);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return mixed
     */
    public function getCalledClass()
    {
        return str_replace('\\', '_', get_called_class());
    }


    /**
     * @return string
     */
    public function handleActionPromise()
    {
        $resolve = <<<'SCRIPT'
var actionResolver = function (data) {

            var response = data[0];
            var target   = data[1];
                
            if (typeof response !== 'object') {
                return Dcat.swal({type: 'error', title: 'Oops!'});Ø
            }
            
            var then = function (then) {
                if (then.action == 'refresh') {
                    Dcat.reload();
                }
                
                if (then.action == 'download') {
                    window.open(then.value, '_blank');
                }
                
                if (then.action == 'redirect') {
                    Dcat.redirect(then.value);
                }
                
                if (then.action == 'location') {
                    window.location = then.value;
                }
            };
            
            if (typeof response.data.message === 'string' && response.data.type) {
                Dcat[response.data.type](response.data.message);
            }
            
            if (response.data.then) {
              then(response.data.then);
            }
        };
        
        var actionCatcher = function (request) {
            if (request && typeof request.responseJSON === 'object') {
                Dcat.toastr.error(request.responseJSON.message, '', {positionClass:"toast-bottom-center", timeOut: 10000}).css("width","500px")
            }
        };
SCRIPT;

        Admin::script($resolve);

        return <<<'SCRIPT'
process.then(actionResolver).catch(actionCatcher);
SCRIPT;
    }

    /**
     * @param Request $request
     *
     * @return $this
     */
    public function validate(Request $request)
    {
        if ($this->interactor instanceof Interactor\Form) {
            $this->interactor->validate($request);
        }

        return $this;
    }

}
