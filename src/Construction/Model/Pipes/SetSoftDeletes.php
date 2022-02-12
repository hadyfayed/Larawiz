<?php

namespace Larawiz\Larawiz\Construction\Model\Pipes;

use Closure;
use Illuminate\Database\Eloquent\SoftDeletes;
use Larawiz\Larawiz\Construction\Model\ModelConstruction;

class SetSoftDeletes
{
    /**
     * Handle the model construction.
     *
     * @param  \Larawiz\Larawiz\Construction\Model\ModelConstruction  $construction
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(ModelConstruction $construction, Closure $next)
    {
        if ($construction->model->softDelete->using) {
            $construction->namespace->addUse(SoftDeletes::class);
            $construction->class->addTrait(SoftDeletes::class);

            if ($construction->model->softDelete->usesNonDefaultColumn()) {
                $construction->class
                    ->addConstant('DELETED_AT', $construction->model->softDelete->column)
                    ->setPublic()
                    ->addComment('The soft delete timestamp column.')
                    ->addComment('')
                    ->addComment('@var string');
            }

            $construction->namespace->addUse('\Illuminate\Support\Carbon');
            $construction->class->addComment(
                '@property-read null|Carbon $' . $construction->model->softDelete->column
            );
        }

        return $next($construction);
    }
}
