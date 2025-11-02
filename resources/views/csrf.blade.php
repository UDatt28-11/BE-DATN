<form action="{{ route('test.csrf') }}" method="POST">
    @csrf
    <button type="submit">Test</button>
</form>
