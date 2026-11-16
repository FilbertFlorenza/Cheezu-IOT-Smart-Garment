@extends('layouts.error')

@section('title', 'Too many requests')
@section('code', 'Error 429')
@section('heading', 'Slow down')
@section('message', 'Too many requests were sent in a short period. Wait a moment and try again.')
