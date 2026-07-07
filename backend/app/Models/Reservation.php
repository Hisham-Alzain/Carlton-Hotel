<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
// P1 STUB — P4 expands this model fully
class Reservation extends Model {
    protected $fillable = ['booking_code','guest_id','last_name','phone'];
}
