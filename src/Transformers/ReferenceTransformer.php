<?php

namespace tricciardi\LaravelMultibanco;

use League\Fractal\TransformerAbstract;
use tricciardi\LaravelMultibanco\Reference;

class ReferenceTransformer extends TransformerAbstract
{

    protected $defaultIncludes = [

    ];

    public function transform(Reference $ref)
    {
        return [
          'id'=> (int) $ref->id,
          'state'=> $ref->state,
          'expiration_date'=> date("d/m/Y",strtotime($ref->expiration_date)),
          'entity'=> $ref->entity,
          'reference'=> format_reference($ref->reference),
          'value'=> (float) $ref->value,
        ];
    }





}
