<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    protected $fillable = ['guardian_name', 'contact_number', 'relationship_to_learner', 'guardian_email', 'occupation'];
}
