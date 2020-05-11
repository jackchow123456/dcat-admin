<?php

namespace Dcat\Admin\Controllers;

use Dcat\Admin\Actions\Action;
use Dcat\Admin\Actions\Response;
use Dcat\Admin\Grid\GridAction;
use Exception;
use Illuminate\Http\Request;

class HandleActionController
{
    /**
     * @param Request $request
     *
     * @return $this|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        $action = $this->resolveActionInstance($request);

        $action->setKey($request->get('_key'));
        $arguments = [];

        if (! $action->passesAuthorization()) {
            return $action->failedAuthorization();
        }

        try {
            $response = $action->validate($request)->handle(
                ...$this->resolveActionArgs($request, ...$arguments)
            );
        } catch (Exception $exception) {
            return Response::withException($exception)->send();
        }

        $response = $action->handle($request);

        return $response instanceof Response ? $response->send() : $response;
    }

    /**
     * @param Request $request
     *
     * @throws Exception
     *
     * @return Action
     */
    protected function resolveActionInstance(Request $request): Action
    {
        if (! $request->has('_action')) {
            throw new Exception('Invalid action request.');
        }

        $actionClass = str_replace('_', '\\', $request->get('_action'));

        if (! class_exists($actionClass)) {
            throw new Exception("Action [{$actionClass}] does not exist.");
        }

        /** @var Action $action */
        $action = app($actionClass);

        if (! method_exists($action, 'handle')) {
            throw new Exception("Action method {$actionClass}::handle() does not exist.");
        }

        return $action;
    }
}
