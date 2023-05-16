<?php

namespace App\Http\Controllers;

use App\Models\Record;
use Illuminate\Http\Request;
use App\Services\RecordService;

class RecordController extends Controller
{
    public $record_service;

    /**
     * 学習記録のコンストラクタ
     *
     * @param RecordService $record_service 学習記録のサービスクラス
     */
    public function __construct(RecordService $record_service)
    {
        $this->record_service = $record_service;
    }

    /**
     * 学習記録一覧画面に遷移
     *
     * @return view 学習記録一覧画面
     */
    public function index()
    {
        $records = $this->record_service->index();
        if ($records === null) {
            return view('records.index')->with('message', '学習記録がありません');
        } else {
            return view('records.index', compact('records'));
        }
    }

    /**
     * 学習記録の詳細画面に遷移
     *
     * @return view 学習詳細画面
     */
    public function show(Record $record)
    {
        $duration = $record->duration;
        $hours = $this->record_service->convertTotalMinutesToHours($duration);
        $minutes = $this->record_service->convertTotalMinutesToMinutes($duration);
        $generated_text = $this->record_service->getGeneratedText($record);
        return view('records.show', compact(
            'record',
            'hours',
            'minutes',
            'generated_text',
        ));
    }

    /**
     * 学習記録を作成してトップページにリダイレクト
     *
     * @return redirect トップページ
     */
    public function store(Request $request)
    {
        $duration = $request->input('duration');
        $total_minute = $this->record_service->convertHHMMToTotalMinute($duration);
        $this->record_service->store($request, $total_minute);
        return redirect()->route('top');
    }

    /**
     * 学習記録フォーム画面に遷移
     *
     * @return view 学習記録フォーム画面
     */
    public function create()
    {
        return view('records.create');
    }

    /**
     * 学習記録編集フォーム画面に遷移
     *
     * @return view 学習記録編集画面
     */
    public function edit(Record $record)
    {
        $duration = $record->duration;
        $hours = $this->record_service->convertTotalMinutesToHours($duration);
        $minutes = $this->record_service->convertTotalMinutesToMinutes($duration);
        return view('records.edit', compact(
            'record',
            'hours',
            'minutes',
        ));
    }

    /**
     * 学習記録を更新してトップページにリダイレクト
     *
     * @return redirect トップページ
     */
    public function update(Request $request, Record $record)
    {
        $this->record_service->update($request, $record);
        return redirect()->route('top');
    }

    /**
     * 学習記録を削除してトップページにリダイレクト
     *
     * @param Record $record 学習記録
     * @param Request $request リクエスト
     * @return redirect トップページ
     */
    public function destroy(Request $request, Record $record)
    {
        $this->record_service->destroyTags($request, $record);
        $record->delete();
        return redirect()->route('top');
    }
}
