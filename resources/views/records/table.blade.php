<div class="row justify-content-center">
  <div class="col-sm-12 col-md-11 col-lg-10 offset-lg-1">
    <hr>
    <table class="table table-borderless">
      <tbody>
        @foreach ($user->records as $record)
        <tr class="h5">
          <td>
            {{ $record->learning_date }}
          </td>
        </tr>
        <tr class="h5">
          <td>学習時間{{ $record->duration }} 分</td>
        </tr>
        <tr>
          <td class="h3"><a href="{{ route('records.show', ['record' => $record->id]) }}">
              {{ $record->title }}
          </td>
        </tr>
        <tr>
          <td>
            {{ $record->body }}
            <hr>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>