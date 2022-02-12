<?php

namespace Larawiz\Larawiz\Construction\Model\Pipes;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\Notifiable;
use Larawiz\Larawiz\Construction\Model\ModelConstruction;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;

class CreateModelInstance
{
    /**
     * Handle the model construction
     *
     * @param  \Larawiz\Larawiz\Construction\Model\ModelConstruction  $construction
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(ModelConstruction $construction, Closure $next)
    {
        $construction->file = new PhpFile();
        $construction->namespace = $construction->file->addNamespace($construction->model->namespace);

        $construction->class = $construction->file->addClass($construction->model->fullNamespace());
        $construction->class->addExtend($construction->model->modelType);

        // If the model is an Authenticatable class instance, we will add the needed classes and traits.
        if ($construction->model->isUser()) {
            $construction->namespace->addUse(User::class, 'Authenticatable');
            $construction->namespace->addUse(MustVerifyEmail::class);
            $construction->namespace->addUse(Notifiable::class);
            $construction->class->addTrait(Notifiable::class);
        } else {
            $construction->namespace->addUse($construction->model->modelType);
        }

        $construction->namespace->addUse('\Illuminate\Database\Eloquent\Builder');
        $this->setBuilderPhpDocs($construction->class);

        return $next($construction);
    }

    /**
     * Set the Eloquent Builder methods to document the return of the model.
     *
     * @param  \Nette\PhpGenerator\ClassType  $class
     */
    protected function setBuilderPhpDocs(ClassType $class)
    {
        $class->addComment('@mixin Builder');

        $class->addComment('');

        $class->addComment('@method static Builder|static query()');
        $class->addComment('@method Builder|static newQuery()');

        $methods = [
            'make(array $attributes = [])',
            'create(array $attributes = [])',
            'forceCreate(array $attributes)',
            'firstOrNew(array $attributes = [], array $values = [])',
            'firstOrFail($columns = [\'*\'])',
            'firstOrCreate(array $attributes, array $values = [])',
            'firstOr($columns = [\'*\'], \Closure $callback = null)',
            'firstWhere($column, $operator = null, $value = null, $boolean = \'and\')',
            'updateOrCreate(array $attributes, array $values = [])',
            'findOrFail($id, $columns = [\'*\'])',
            'findOrNew($id, $columns = [\'*\'])',
        ];



        foreach ($methods as $method) {
            $class->addComment("@method static {$method}");
        }

        $methods = [
            'first($columns = [\'*\'])',
            'find($id, $columns = [\'*\'])',
        ];

        foreach ($methods as $method) {
            $class->addComment("@method null|static {$method}");
        }

        $class->addComment('');
    }
}
