@extends('errors.layout')

@section('title', 'Forbidden')
@section('heading', 'Access Restricted')
@section('message', $message ?? 'You do not have permission to access this area.')
