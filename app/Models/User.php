<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'ID_Number',
        'address',
        'ID_copy',
        'status',
        'salary_payment_day',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function accountDetails()
    {
        return $this->hasMany(AccountDetail::class);
    }

    public function loanDisbursements()
    {
        return $this->hasMany(LoanDisbursement::class, 'approver_id');
    }

    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function customerProfile()
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function customerDocuments()
    {
        return $this->hasMany(CustomerDocument::class);
    }

    // ── Role helpers ──

    public function hasRole(string ...$roles): bool
    {
        $role = $this->system_role ?? ($this->rule_id === 2 ? 'admin' : 'client');

        return $role === 'admin' || in_array($role, $roles);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isLoanOfficer(): bool
    {
        return $this->hasRole('loan_officer');
    }

    public function isFinance(): bool
    {
        return $this->hasRole('finance');
    }

    public function isClient(): bool
    {
        return ($this->system_role ?? 'client') === 'client';
    }
}
