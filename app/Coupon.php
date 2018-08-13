<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    //
   protected $fillable = ['coupon_code', 'state', 'discount', 'coupon_group', 'event_id', 'ticket', 'exact_amount', 'ticket_id'];
}
