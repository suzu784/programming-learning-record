<?php

namespace App\Services;

use App\Http\Requests\RecordRequest;
use Carbon\Carbon;
use App\Models\Tag;
use App\Models\Record;
use App\Models\ChatGPT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RecordService
{
  public $flash_message;

  /**
   * 学習記録サービスクラスのコンストラクタ
   */
  public function __construct()
  {
    $this->flash_message = null;
  }

  /**
   * 1週間の日付を取得
   *
   * @return $week_date 1週間の日付
   */
  public function getWeekDate()
  {
    // 現在の日付を取得
    $start_date = Carbon::today()->subDay(6);
    // 1週間後の日付を計算
    $end_date = $start_date->copy()->addDay(6);

    // 1週間の日付を取得
    $week_date = [];
    while ($start_date->lte($end_date)) {
      $week_date[] = $start_date->copy()->toDateString();
      $start_date->addDay();
    }
    return $week_date;
  }

  /**
   * 1日あたりの合計学習時間を取得
   *
   * @return $total_study_time 1日の合計学習時間
   */
  public function getTotalStudyTime()
  {
    $week_date = $this->getWeekDate(); // 1週間の日付を取得
    $total_study_time = [];

    foreach ($week_date as $date) { // 1日の合計学習時間を取得
      $record = Record::NotDraft()->whereDate('learning_date', $date)
        ->where('user_id', Auth::id())
        ->groupBy('learning_date')
        ->select('learning_date', DB::raw('SUM(duration) as total_duration'))
        ->first();

      if (is_null($record)) {
        array_push($total_study_time, 0);
      } else {
        array_push($total_study_time, $record->total_duration);
      }
    }
    return $total_study_time;
  }

  /**
   * 合計時間を分から時間に変換する
   *
   * @param Record $duration 合計時間(HH:MM)
   * @return $hours 時間
   */
  public function convertTotalMinutesToHours($duration)
  {
    $hours = floor($duration / 60);
    return $hours;
  }

  /**
   * 合計時間を分から時間に変換する
   *
   * @param Record $duration 合計時間(HH:MM)
   * @return $minutes 分
   */
  public function convertTotalMinutesToMinutes($duration)
  {
    $minutes = $duration % 60;
    return $minutes;
  }

  /**
   * HH:MMから合計時間に変換する
   *
   * @param Record $duration 合計時間(HH:MM)
   * @return $total_minute 合計時間
   */
  public function convertHHMMToTotalMinute($duration)
  {
    $duration_arr = explode(':', $duration); // ":"で分割し、時間と分を配列で取得する
    $hours = $duration_arr[0]; // 時間を取得する
    $minutes = $duration_arr[1]; // 分を取得する
    $total_minute = ($hours * 60) + $minutes; // 時間を60で乗じて分に変換し、分と合算する
    return $total_minute;
  }

  /**
   * 学習記録に紐づくChatGPTのレビューを取得
   *
   * @param Record $record 学習記録
   * @return $generated_text レビュー
   */
  public function getGeneratedText(Record $record)
  {
    $chat_gpt_record = ChatGPT::where('record_id', $record->id)->first();
    if ($chat_gpt_record) {
      $generated_text = $chat_gpt_record->content;
      return $generated_text;
    } else {
      return null;
    }
  }

  /**
   * フラッシュメッセージを取得
   *
   * @return $flash_message フラッシュメッセージ
   */
  public function getFlashMessage()
  {
    return $this->flash_message;
  }

  /**
   * カンマを区切って配列に変換
   *
   * @param $tag_name タグ
   * @return $tag_array タグ(配列)
   */
  public function stringToArray($tag_name)
  {
    $tag_array = explode(',', $tag_name);
    return $tag_array;
  }

  /**
   * タグの作成または取得、学習記録との関連付けを行う
   *
   * @param Request $request リクエスト
   * @param Record $record 学習記録
   * @return void
   */
  public function processTags(Request $request, Record $record)
  {
    $tag_name = $request->input('tagName');
    $tag_array = $this->stringToArray($tag_name);

    foreach ($tag_array as $name) {
      $tag = Tag::firstOrCreate(['name' => $name]);
      $record->tags()->attach($tag->id);
    }
  }

  /**
   * 学習記録のタグを生成
   *
   * @param Request $request リクエスト
   * @param Record $record 学習記録
   * @return void
   */
  public function createTags(Request $request, Record $record)
  {
    $this->processTags($request, $record);
  }

  /**
   * 学習記録のタグを更新
   *
   * @param Request $request リクエスト
   * @param Record $record 学習記録
   * @return void
   */
  public function updateTags(Request $request, Record $record)
  {
    $this->destroyTags($request, $record);
    $this->processTags($request, $record);
  }

  /**
   * 学習記録とタグのリレーションを解除
   *
   * @param Request $request リクエスト
   * @param Record $record 学習記録
   * @return void
   */
  public function destroyTags(Request $request, Record $record)
  {
    $record->tags()->detach($request->input('tagId'));
  }

  /**
   * 学習記録一覧を取得
   *
   * @param Request $request リクエスト
   * @return $records 学習記録一覧
   */
  public function index(Request $request)
  {
    $keyword = $request->input('keyword');
    $query = Record::query();
    if (!empty($keyword)) {
      $query->where('title', 'LIKE', '%' . $keyword . '%')
        ->orWhere('body', 'LIKE', '%' . $keyword . '%')
        ->orWhereHas('user', function ($query) use ($keyword) {
          $query->where('name', 'LIKE', '%' . $keyword . '%');
        });
    }
    $records = $query->NotDraft()->orderBy('created_at', 'desc')->paginate(8);
    return $records;
  }

  /**
   * 学習記録を作成
   *
   * @param RecordRequest $record_request リクエスト
   * @param $total_minute 合計時間
   * @return void
   */
  public function store(RecordRequest $record_request, $total_minute)
  {
    $record = Record::create([
      'user_id' => Auth::id(),
      'title' => $record_request->input('title'),
      'body' => $record_request->input('body'),
      'is_draft' => $record_request->has('is_draft'),
      'learning_date' => $record_request->input('learning_date'),
      'duration' => $total_minute,
    ]);

    if ($record_request->has('is_draft')) {
      $this->flash_message = '下書きを保存しました';
    } else {
      $this->flash_message = '学習を記録しました';
    }

    $tag_name = $record_request->input('tagName');
    if ($tag_name) {
      $this->createTags($record_request, $record);
    }
  }

  /**
   * 学習記録を更新
   *
   * @param RecordRequest $record_request リクエスト
   * @param Record $record 学習記録
   * @return void
   */
  public function update(RecordRequest $record_request, Record $record)
  {
    $duration = $record_request->input('duration');
    $total_minute = $this->convertHHMMToTotalMinute($duration);
    $record->fill(array_merge($record_request->except('is_draft'), ['duration' => $total_minute]))->save();

    $tag_id = $record_request->input('tagId');
    $tag_name = $record_request->input('tagName');
    if ($tag_id) { // タグが存在する場合
      if (is_null($tag_name)) {
        $this->destroyTags($record_request, $record);
        return;
      }
      $this->updateTags($record_request, $record);
    } elseif ($tag_name) { // タグが存在しない場合
      $this->createTags($record_request, $record);
    }

    if ($record_request->has('is_draft')) { // 下書き保存の場合
      $this->flash_message = '下書きを保存しました';
      return;
    }
    $record->is_draft = false; // 学習を記録する場合
    $record->save();
    $this->flash_message = '学習を記録しました';
  }
}
