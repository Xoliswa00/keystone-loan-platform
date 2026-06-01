<!-- resources/views/loan_repayments/index.blade.php -->

@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Loan Repayments</h1>
    <a href="{{ route('loanrepayments.create') }}" class="btn btn-primary">Add Loan Repayment</a>

    @if(session('success'))
        <div class="alert alert-success mt-3">
            {{ session('success') }}
        </div>
    @endif

    <table class="table table-bordered mt-3">
        <thead>
            <tr>
                <th>Loan ID</th>
                <th>Amount</th>
                <th>Repayment Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($loanRepayments as $repayment)
                <tr>
                    <td>{{ $repayment->loan->id }}</td>
                    <td>{{ number_format($repayment->amount, 2) }}</td>
                    <td>{{ $repayment->repayment_date }}</td>
                    <td>{{ ucfirst($repayment->status) }}</td>
                    <td>
                        <a href="{{ route('loanrepayments.edit', $repayment) }}" class="btn btn-warning btn-sm">Edit</a>
                        <form action="{{ route('loanrepayments.destroy', $repayment) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
