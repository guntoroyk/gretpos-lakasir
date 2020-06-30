<?php

namespace App\Abstracts;

use App\Interfaces\Repository as RepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class Repository implements RepositoryInterface
{
    /** @var string model */
    protected string $model;

    public function find(int $id)
    {
        return $this->model::find($id);
    }

    public function delete(int $id)
    {
        return $this->model::find($id)->delete();
    }

    public function paginate(Request $request, array $columns = ['*'], string $search)
    {
        $self = $this;
        return $this->model::select($columns)
                    ->when(isset($this->parent) && ! is_null($this->parent), function ($query)
                        use ($self) {
                            return $query->where($self->column, $self->parent->id);
                        })
                    ->when(! is_null($request->s), function ($query)
                        use ($request, $search) {
                            return $query->where($search, 'LIKE', $request->s.'%%');
                        })
                        ->orderBy('id', 'desc')
                        ->paginate($request->per_page);
    }

    public function all(array $columns)
    {
        return $this->model::select($columns)->get();
    }

    public function get($request, $columns, $search)
    {
        return $this->model::select($columns)->when(! is_null($request->s), function ($query)
            use ($request, $search) {
                return $query->where($search, 'LIKE', $request->s.'%%');
            })
            ->orderBy('id', 'desc')
            ->get();
    }

    public function create(Request $request)
    {
        $model = new $this->model;
        $model->fill($request->all());
        if (isset($this->parent)) {
            $model->{strtolower(class_basename($this->parent))}()->associate($this->parent);
        }
        $model->save();

        return $model;
    }

    public function update(Request $request, $model)
    {
        $model->fill($request->all());
        if (isset($this->parent)) {
            $model->{strtolower(class_basename($this->parent))}()->associate($this->parent);
        }
        $model->save();

        return $model;
    }

    public function bulkDestroy(Request $request, string $column = 'id')
    {
        $self = $this;
        DB::transaction(static function () use ($request, $self, $column) {
            collect($request->ids)
                ->chunk(1000)
                ->each(static function ($bulkChunk) use ($self, $column) {
                    $self->model::whereIn($column, $bulkChunk)->delete();
                });
        });
    }
}
