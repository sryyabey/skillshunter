@if (session('status'))
  <p role="status" class="my-3 text-sm text-emeraldTrust">{{ session('status') }}</p>
@endif
@if ($errors->any())
  <ul role="alert" class="my-3 list-disc pl-5 text-sm text-red-800">
    @foreach ($errors->all() as $message)
      <li>{{ $message }}</li>
    @endforeach
  </ul>
@endif
