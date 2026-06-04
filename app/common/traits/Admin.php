<?php

namespace app\common\traits;

use app\common\model\Dataexport;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use support\Response;
use support\think\Db;
use Throwable;

/**
 * 后台控制器trait类
 * 已导入到 @see \app\common\controller\Admin 中
 * 若需修改此类方法：请复制方法至对应控制器后进行重写
 */
trait Admin
{
    /**
     * 排除入库字段
     * @param array $params
     * @return array
     */
    protected function excludeFields(array $params): array
    {
        if (!is_array($this->preExcludeFields)) {
            $this->preExcludeFields = explode(',', (string)$this->preExcludeFields);
        }

        foreach ($this->preExcludeFields as $field) {
            if (array_key_exists($field, $params)) {
                unset($params[$field]);
            }
        }
        return $params;
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): Response
    {
        if ($this->request->input('select')) {
            return $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        return $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => \get_route_remark(),
        ]);
    }

    /**
     * 添加
     */
    public function add(): Response
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                return $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data = $this->excludeFields($data);
            if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                $data[$this->dataLimitField] = $this->auth->id;
            }

            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('add');
                        $validate->check($data);
                    }
                }
                $result = $this->model->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                return $this->error($e->getMessage());
            }
            if ($result !== false) {
                return $this->success(__('Added successfully'));
            } else {
                return $this->error(__('No rows were added'));
            }
        }

        return $this->error(__('Parameter error'));
    }

    /**
     * 编辑
     * @throws Throwable
     */
    public function edit(): Response
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->input($pk);
        $row = $this->model->find($id);
        if (!$row) {
            return $this->error(__('Record not found'));
        }

        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds && !in_array($row[$this->dataLimitField], $dataLimitAdminIds)) {
            return $this->error(__('You have no permission'));
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                return $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data   = $this->excludeFields($data);
            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('edit');
                        $data[$pk] = $row[$pk];
                        $validate->check($data);
                    }
                }
                $result = $row->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                return $this->error($e->getMessage());
            }
            if ($result !== false) {
                return $this->success(__('Update successful'));
            } else {
                return $this->error(__('No rows updated'));
            }
        }

        return $this->success('', [
            'row' => $row
        ]);
    }

    /**
     * 删除
     * @throws Throwable
     */
    public function del(): Response
    {
        $where             = [];
        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds) {
            $where[] = [$this->dataLimitField, 'in', $dataLimitAdminIds];
        }

        $ids     = $this->request->input('ids', []);
        $where[] = [$this->model->getPk(), 'in', $ids];
        $data    = $this->model->where($where)->select();

        $count = 0;
        $this->model->startTrans();
        try {
            foreach ($data as $v) {
                $count += $v->delete();
            }
            $this->model->commit();
        } catch (Throwable $e) {
            $this->model->rollback();
            return $this->error($e->getMessage());
        }
        if ($count) {
            return $this->success(__('Deleted successfully'));
        } else {
            return $this->error(__('No rows were deleted'));
        }
    }

    /**
     * 排序 - 增量重排法
     * @throws Throwable
     */
    public function sortable(): Response
    {
        $pk        = $this->model->getPk();
        $move      = $this->request->input('move');
        $target    = $this->request->input('target');
        $order     = $this->request->input("order") ?: $this->defaultSortField;
        $direction = $this->request->input('direction');

        $dataLimitWhere    = [];
        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds) {
            $dataLimitWhere[] = [$this->dataLimitField, 'in', $dataLimitAdminIds];
        }

        $moveRow   = $this->model->where($dataLimitWhere)->find($move);
        $targetRow = $this->model->where($dataLimitWhere)->find($target);

        if ($move == $target || !$moveRow || !$targetRow || !$direction) {
            return $this->error(__('Record not found'));
        }

        // 当前是否以排序字段排序（只检查当前排序和默认排序字段，不检查有序保证字段）
        if ($order && is_string($order)) {
            $order = explode(',', $order);
            $order = [$order[0] => $order[1] ?? 'asc'];
        }
        if (!array_key_exists($this->sortField, $order)) {
            return $this->error(__('Please use the %s field to sort before operating', [$this->sortField]));
        }

        // 开始增量重排
        $order     = $this->queryOrderBuilder();
        $sortValue = $targetRow[$this->sortField];

        // 波及行的排序值向上增加还是向下减少
        if ($order[$this->sortField] == 'desc') {
            $updateMethod = $direction == 'up' ? 'dec' : 'inc';
        } else {
            $updateMethod = $direction == 'up' ? 'inc' : 'dec';
        }

        // 与目标行排序值相同的行
        $sortRowIds    = $this->model
            ->where($dataLimitWhere)
            ->where($this->sortField, $sortValue)
            ->order($order)
            ->column($pk);
        $sortRowsCount = count($sortRowIds);

        // 单个 SQL 查询中完成大于目标排序值行的修改
        $this->model->where($dataLimitWhere)
            ->where($this->sortField, $updateMethod == 'dec' ? '<' : '>', $sortValue)
            ->whereNotIn($pk, [$moveRow->$pk])
            ->$updateMethod($this->sortField, $sortRowsCount)
            ->save();

        // 遍历与目标行排序值相同的行，每出现一行排序值将额外 +1，保证排序值相同行的顺序位置不变
        if ($direction == 'down') {
            $sortRowIds = array_reverse($sortRowIds);
        }

        $moveComplete = 0;
        $sortRowIds   = implode(',', $sortRowIds);
        $sortRows     = $this->model->where($dataLimitWhere)
            ->where($pk, 'in', $sortRowIds)
            ->orderRaw("field($pk,$sortRowIds)")
            ->select();

        // 排序值相等行
        foreach ($sortRows as $key => $sortRow) {
            // 跳过当前拖动行（相等排序值数据之间的拖动时，被拖动行会出现在 $sortRows 内）
            if ($moveRow[$pk] == $sortRow[$pk]) {
                continue;
            }

            if ($updateMethod == 'dec') {
                $rowSortVal = $sortRow[$this->sortField] - $key;
            } else {
                $rowSortVal = $sortRow[$this->sortField] + $key;
            }

            // 找到了目标行
            if ($sortRow[$pk] == $targetRow[$pk]) {
                $moveComplete             = 1;
                $moveRow[$this->sortField] = $rowSortVal;
                $moveRow->save();
            }

            $rowSortVal                 = $updateMethod == 'dec' ? $rowSortVal - $moveComplete : $rowSortVal + $moveComplete;
            $sortRow[$this->sortField] = $rowSortVal;
            $sortRow->save();
        }

        return $this->success();
    }

    /**
     * 加载为select(远程下拉选择框)数据，默认还是走$this->index()方法
     * 必要时请在对应控制器类中重写
     */
    public function select(): Response
    {
        return $this->success('', []);
    }

    /**
     * 复制
     * @throws Throwable
     */
    public function copy(): Response
    {
        $pk  = $this->model->getPk();
        $ids = $this->request->input('ids', []);
        $ids = is_array($ids) ? $ids : explode(',', (string)$ids);
        $ids = array_values(array_filter($ids, fn($id) => $id !== ''));
        if (!$ids) {
            return $this->error(__('Parameter %s can not be empty', ['ids']));
        }

        $where = [[$pk, 'in', $ids]];
        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds) {
            $where[] = [$this->dataLimitField, 'in', $dataLimitAdminIds];
        }

        $rows  = $this->model->where($where)->select();
        $count = 0;
        $this->model->startTrans();
        try {
            foreach ($rows as $row) {
                $this->model->create($this->prepareCopyData($row->toArray(), $pk));
                $count++;
            }
            $this->model->commit();
        } catch (Throwable $e) {
            $this->model->rollback();
            return $this->error($e->getMessage());
        }

        return $count ? $this->success(__('Copied successfully')) : $this->error(__('No rows were copied'));
    }

    /**
     * 导出
     * @throws Throwable
     */
    public function export(): Response
    {
        $id         = (int)$this->request->input('id', 0);
        $subId      = (int)$this->request->input('subId', 0);
        $download   = filter_var($this->request->input('download', false), FILTER_VALIDATE_BOOLEAN);
        $getTaskZip = filter_var($this->request->input('getTaskZip', false), FILTER_VALIDATE_BOOLEAN);

        if ($id) {
            return $getTaskZip ? $this->getTaskZip($id) : $this->exportTask($id, $subId, $download);
        }

        [$title, $columns, $order, $query] = $this->buildExportData();
        if (!$title || !$columns) {
            return $this->error(__('Parameter error'));
        }
        $exportNumber = $query->count();
        if (!$exportNumber) {
            return $this->error(__('No data needs to be exported'));
        }

        $xlsMaxNumber = max(1, (int)config('xframe.xls_max_number', 10000));
        $xlsMaxNumber = min($xlsMaxNumber, $exportNumber);
        $subtaskCount = (int)ceil($exportNumber / $xlsMaxNumber);

        $subtask = [];
        for ($i = 0; $i < $subtaskCount; $i++) {
            $offset      = $i * $xlsMaxNumber;
            $subtask[$i] = [
                'id'     => $i,
                'status' => 0,
                'min'    => $offset,
                'max'    => $xlsMaxNumber,
                'sql'    => $query->fetchSql()->limit($offset, $xlsMaxNumber)->order($order)->select(),
            ];
        }

        $task = new Dataexport();
        $task->save([
            'name'           => $this->exportSheetTitle((string)$title),
            'columns'        => $columns,
            'memory_limit'   => (int)config('xframe.memory_limit', 1024),
            'xls_max_number' => $xlsMaxNumber,
            'export_number'  => $exportNumber,
            'subtask'        => $subtask,
            'subtask_count'  => $subtaskCount,
            'progress'       => 0,
            'file'           => '',
            'create_time'    => date('Y-m-d H:i:s'),
            'admin_id'       => $this->auth->id,
        ]);

        $data = [
            'download' => $subtaskCount === 1,
            'id'       => $task->id,
        ];

        if (!$data['download']) {
            $this->deleteDirectory($this->exportRuntimeDir((int)$task->id));
            $this->ensureDirectory($this->exportRuntimeDir((int)$task->id));

            $taskData = $task->toArray();
            $currentPage = 0;
            $subtaskPage = [];
            foreach ($taskData['subtask'] as $value) {
                $value['status'] = 0;
                $subtaskPage[$currentPage][] = $value;
                if (count($subtaskPage[$currentPage]) >= 2) {
                    $currentPage++;
                }
            }

            $taskData['subtask_progress'] = round(92 / $subtaskCount, 2);
            $taskData['lastprogress']     = 5;
            $data['task']                 = $taskData;
            $data['subtaskPage']          = $subtaskPage;
        }

        return $this->success(__('Export task initializing'), $data);
    }

    /**
     * 导出子任务
     */
    public function exportTask(int $id, int $subId, bool $download): Response
    {
        $dataExportModel = new Dataexport();
        $task = $dataExportModel->where('id', $id)->where('admin_id', $this->auth->id)->find();
        if (!$task) {
            return $this->error(__('Export task not found, please restart'), ['id' => $id]);
        }

        if (!isset($task->subtask[$subId]) || !is_array($task->subtask[$subId])) {
            return $this->error(__('Export subtask not found, please restart'), ['subId' => $subId]);
        }

        $subTask  = $task->subtask[$subId];
        $taskName = $this->exportSheetTitle((string)$task->name);
        $columns  = $task->columns;

        set_time_limit(0);
        ini_set('memory_limit', max(64, (int)$task->memory_limit) . 'M');

        $saveDir = $this->exportRuntimeDir($id);
        $this->ensureDirectory($saveDir);
        $file = $saveDir . DIRECTORY_SEPARATOR . $subId . '.xlsx';

        if (!$download) {
            if (($subTask['status'] ?? 0) == 1) {
                return $this->error(__('Export subtask is running'), ['subId' => $subId]);
            }
            if (($subTask['status'] ?? 0) == 2 && is_file($file)) {
                return $this->error(__('Export subtask has already been processed'), ['subId' => $subId]);
            }
        }

        $spreadsheet = new Spreadsheet();
        $worksheet   = $spreadsheet->getActiveSheet();
        $worksheet->setTitle($taskName);

        foreach ($columns as $index => $column) {
            $worksheet->setCellValue([$index + 1, 1], $column['title']);
        }

        try {
            $rows = Db::query($subTask['sql']);
        } catch (Throwable $e) {
            $spreadsheet->disconnectWorksheets();
            return $this->error(__('Export task failed'), ['subId' => $subId, 'msg' => $e->getMessage()]);
        }

        $y = 2;
        foreach ($rows as $rowKey => $row) {
            $row = $this->exportData($row);
            foreach ($columns as $index => $column) {
                $field = str_replace('.', '__', $column['field']);
                $value = $row[$field] ?? '';
                $this->writeExportCell($worksheet, $index + 1, $y, $value, $column);
            }
            $y++;
            unset($rows[$rowKey]);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($file);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if ($download) {
            return response()->download($file, $id . '.' . $taskName . '.xlsx');
        }

        $result = false;
        $this->model->startTrans();
        try {
            $task = $dataExportModel->find($id);
            if ($task && isset($task->subtask[$subId]) && is_array($task->subtask[$subId])) {
                $subtaskTemp = $task->subtask;
                $subtaskTemp[$subId]['status'] = 2;
                $progress = $task->progress + round(92 / count($task->subtask), 2);
                $task->subtask  = $subtaskTemp;
                $task->progress = min(100, $progress);
                $task->save();
                $result = true;
            }
            $this->model->commit();
        } catch (Throwable $e) {
            $this->model->rollback();
            return $this->error(__('Export task failed'), ['subId' => $subId, 'msg' => $e->getMessage()]);
        }

        return $result ? $this->success('', ['subId' => $subId]) : $this->error('', ['subId' => $subId]);
    }

    /**
     * 获取导出任务压缩包
     */
    public function getTaskZip(int $id): Response
    {
        $task = (new Dataexport())->where('id', $id)->where('admin_id', $this->auth->id)->find();
        if (!$task) {
            return $this->error(__('Export task not found, please restart'));
        }
        if (!isset($task->subtask) || !is_array($task->subtask)) {
            return $this->error(__('Export task not found, please restart'));
        }
        if ($task->file) {
            return $this->success('', ['url' => $task->file]);
        }

        $taskDir = $this->exportRuntimeDir($id);
        foreach ($task->subtask as $subtask) {
            if (!is_file($taskDir . DIRECTORY_SEPARATOR . $subtask['id'] . '.xlsx')) {
                return $this->error(__('Export subtasks are not finished'));
            }
        }

        $zipDir = $this->exportZipDir();
        $this->ensureDirectory($zipDir);
        $zipFileName = substr(hash('sha256', $this->auth->id . '_' . $id . '_' . microtime(true) . '_' . uniqid('', true)), 0, 32) . '.export.zip';
        $zipPath = $zipDir . DIRECTORY_SEPARATOR . $zipFileName;

        $zip = new ZipFile();
        try {
            foreach (glob($taskDir . DIRECTORY_SEPARATOR . '*.xlsx') ?: [] as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->saveAsFile($zipPath);
            $this->deleteDirectory($taskDir);
        } catch (ZipException $e) {
            return $this->error(__('Export package failed: %s', [$e->getMessage()]));
        } finally {
            $zip->close();
        }

        $task->file        = $this->exportPublicUrl($zipFileName);
        $task->progress    = 100;
        $task->export_time = date('Y-m-d H:i:s');
        $task->save();

        return $this->success('', ['url' => $task->file]);
    }

    /**
     * 为导出构建基础数据，必要时请在对应控制器类中重写
     */
    public function buildExportData(): array
    {
        $title = $this->request->input('title');
        $column = $this->request->input('column', []);
        if (!$title || !is_array($column)) {
            return ['', [], [], $this->model->whereRaw('1 = 0')];
        }

        [$where, $alias, $limit, $order] = $this->queryBuilder();
        $where = $this->exportWhere($where);
        $query = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where);

        return [$title, $this->buildExportColumns($column), $order, $query];
    }

    /**
     * 导出数据查询构建器，必要时请在对应控制器类中重写
     */
    public function exportWhere($where = [])
    {
        return $where;
    }

    /**
     * 导出数据构建器，必要时请在对应控制器类中重写
     */
    public function exportData($row)
    {
        return $row;
    }

    protected function prepareCopyData(array $data, string $pk): array
    {
        unset($data[$pk], $data['create_time'], $data['update_time']);
        return $data;
    }

    protected function buildExportColumns(array $columns): array
    {
        $exportColumns = [];
        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $export = $column['export'] ?? null;
            if ($export === false || (($column['show'] ?? true) === false && $export !== true)) {
                continue;
            }
            if (isset($column['type']) || !isset($column['prop']) || !isset($column['label'])) {
                continue;
            }
            if (($column['render'] ?? '') === 'slot' || ($column['render'] ?? '') === 'buttons') {
                continue;
            }

            $data = [
                'title'   => $column['label'],
                'field'   => $column['prop'],
                'discern' => 'text',
            ];

            if (($column['render'] ?? '') === 'datetime') {
                $data['discern'] = 'datetime';
            } elseif (($column['render'] ?? '') === 'tag' && (isset($column['replaceValue']) || isset($column['custom']))) {
                $data['discern'] = 'tag';
                $data['custom']  = $column['replaceValue'] ?? $column['custom'];
            }

            $exportColumns[] = $data;
        }

        return $exportColumns;
    }

    protected function exportPublicUrl(string $fileName): string
    {
        return '/export/' . str_replace(['\\', '/'], '', $fileName);
    }

    protected function exportRuntimeDir(?int $id = null): string
    {
        $path = runtime_path() . DIRECTORY_SEPARATOR . 'export';
        return $id === null ? $path : $path . DIRECTORY_SEPARATOR . $id;
    }

    protected function exportZipDir(): string
    {
        return base_path('public' . DIRECTORY_SEPARATOR . 'export');
    }

    protected function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    protected function deleteDirectory(string $dirname, bool $deleteSelf = true): bool
    {
        if (!is_dir($dirname)) {
            return false;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirname, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            if ($fileInfo->isDir()) {
                @rmdir($fileInfo->getRealPath());
            } else {
                @unlink($fileInfo->getRealPath());
            }
        }

        return !$deleteSelf || @rmdir($dirname);
    }

    protected function exportSheetTitle(string $title): string
    {
        $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $title) ?: 'export';
        return mb_substr($title, 0, 31);
    }

    protected function writeExportCell($worksheet, int $x, int $y, mixed $value, array $column): void
    {
        switch ($column['discern'] ?? 'text') {
            case 'int':
                $worksheet->setCellValueExplicit([$x, $y], (int)$value, DataType::TYPE_NUMERIC);
                break;
            case 'datetime':
                if ($value) {
                    $worksheet->setCellValue([$x, $y], is_numeric($value) ? date('Y-m-d H:i:s', (int)$value) : $value);
                } else {
                    $worksheet->setCellValue([$x, $y], '-');
                }
                break;
            case 'tag':
                $worksheet->setCellValue([$x, $y], ($column['custom'][$value] ?? ''));
                break;
            default:
                $worksheet->setCellValueExplicit([$x, $y], (string)$value, DataType::TYPE_STRING);
                break;
        }
    }
}
