<?php

namespace core\crud;

class StubRenderer
{
    public function render(array $descriptor): array
    {
        $names = $this->names($descriptor['app'], $descriptor['path']);
        $i18nPath = str_replace('/', '.', $descriptor['path']);
        $menuName = $this->menuName($descriptor['app'], $descriptor['path']);
        $apiUrl = '/' . $descriptor['app'] . '/' . $names['controllerRoute'] . '/';

        $files = [
            $names['controllerFile'] => $this->controller($descriptor, $names),
            $names['modelFile'] => $this->model($descriptor, $names),
            $names['validateFile'] => $this->validate($descriptor, $names),
            $names['indexFile'] => $this->indexVue($descriptor, $names, $i18nPath, $apiUrl, $menuName),
            $names['formFile'] => $this->formVue($descriptor, $i18nPath),
            $names['zhLangFile'] => $this->langFile($descriptor, $i18nPath, 'zh-cn'),
            $names['enLangFile'] => $this->langFile($descriptor, $i18nPath, 'en'),
        ];

        return [
            'files' => $files,
            'names' => $names + [
                'i18nPath' => $i18nPath,
                'menuName' => $menuName,
                'apiUrl' => $apiUrl,
            ],
        ];
    }

    public function names(string $app, string $path): array
    {
        $segments = explode('/', $path);
        $last = array_pop($segments);
        $class = $this->studly($last);
        $dir = implode('/', $segments);
        $namespaceDir = $segments ? '\\' . implode('\\', $segments) : '';
        $routeDir = $segments ? implode('/', $segments) . '/' : '';

        return [
            'class' => $class,
            'controllerNamespace' => 'app\\' . $app . '\\controller' . $namespaceDir,
            'modelNamespace' => 'app\\' . $app . '\\model' . $namespaceDir,
            'validateNamespace' => 'app\\' . $app . '\\validate' . $namespaceDir,
            'controllerFile' => 'app/' . $app . '/controller/' . ($dir ? $dir . '/' : '') . $class . '.php',
            'modelFile' => 'app/' . $app . '/model/' . ($dir ? $dir . '/' : '') . $class . '.php',
            'validateFile' => 'app/' . $app . '/validate/' . ($dir ? $dir . '/' : '') . $class . '.php',
            'indexFile' => 'web/src/views/' . $app . '/' . $path . '/index.vue',
            'formFile' => 'web/src/views/' . $app . '/' . $path . '/form.vue',
            'zhLangFile' => 'web/src/lang/' . $app . '/zh-cn/' . $path . '.ts',
            'enLangFile' => 'web/src/lang/' . $app . '/en/' . $path . '.ts',
            'controllerRoute' => $routeDir . $class,
            'backendControllerClass' => $this->studly($app),
        ];
    }

    public function menuName(string $app, string $path): string
    {
        return $path;
    }

    protected function controller(array $descriptor, array $names): string
    {
        $class = $names['class'];
        $backendControllerClass = $names['backendControllerClass'];
        $quickSearch = $this->phpArray($descriptor['quickSearch']);
        $preExclude = array_values(array_filter(['create_time', 'update_time'], static fn($field) => isset($descriptor['fields'][$field])));
        $preExcludeCode = $preExclude ? "\n    protected string|array \$preExcludeFields = " . $this->phpArray($preExclude) . ";\n" : '';
        $sortCode = '';
        if ($descriptor['sortField']) {
            $sortCode .= "\n    protected string \$sortField = '" . $this->phpString($descriptor['sortField']) . "';\n";
            $sortCode .= "\n    protected string|array \$defaultSortField = ['" . $this->phpString($descriptor['sortField']) . "' => 'desc'];\n";
        } else {
            $sortCode .= "\n    protected string|array \$defaultSortField = ['" . $this->phpString($descriptor['pk']) . "' => 'desc'];\n";
        }

        return <<<PHP
<?php

namespace {$names['controllerNamespace']};

use {$names['modelNamespace']}\\{$class} as {$class}Model;
use app\\common\\controller\\{$backendControllerClass} as ParentController;

/**
 * {$descriptor['title']}
 */
class {$class} extends ParentController
{
    /**
     * @var object
     * @phpstan-var {$class}Model
     */
    protected object \$model;
{$preExcludeCode}
    protected string|array \$quickSearchField = {$quickSearch};
{$sortCode}
    protected bool \$modelSceneValidate = true;

    public function initialize(): void
    {
        parent::initialize();
        \$this->model = new {$class}Model();
    }
}

PHP;
    }

    protected function model(array $descriptor, array $names): string
    {
        $class = $names['class'];
        $pkCode = $descriptor['pk'] === 'id' ? '' : "\n    protected \$pk = '" . $this->phpString($descriptor['pk']) . "';\n";
        $timestamp = ($descriptor['hasCreateTime'] || $descriptor['hasUpdateTime']) ? 'true' : 'false';
        $createTime = $descriptor['hasCreateTime'] ? '' : "\n    protected \$createTime = false;";
        $updateTime = $descriptor['hasUpdateTime'] ? '' : "\n    protected \$updateTime = false;";
        $typeCode = $this->modelTypeCode($descriptor['fields']);

        return <<<PHP
<?php

namespace {$names['modelNamespace']};

use think\\Model;

/**
 * {$descriptor['title']}
 */
class {$class} extends Model
{{$pkCode}
    protected \$name = '{$this->phpString($descriptor['table'])}';

    protected \$autoWriteTimestamp = {$timestamp};{$createTime}{$updateTime}
{$typeCode}}

PHP;
    }

    protected function validate(array $descriptor, array $names): string
    {
        $class = $names['class'];
        $rules = [];
        $fields = [];
        $scene = [];
        foreach ($descriptor['fields'] as $field) {
            if (!$field['formVisible']) {
                continue;
            }
            $fields[$field['name']] = $field['label'];
            if ($field['required']) {
                $rules[$field['name']] = 'require';
                $scene[] = $field['name'];
            }
        }

        return <<<PHP
<?php

namespace {$names['validateNamespace']};

use think\\Validate;

class {$class} extends Validate
{
    protected \$failException = true;

    protected \$rule = {$this->phpAssoc($rules, 2)};

    protected \$message = [];

    protected \$field = [];

    protected \$scene = [
        'add' => {$this->phpArray($scene, 2)},
        'edit' => {$this->phpArray($scene, 2)},
    ];

    public function __construct()
    {
        \$this->field = {$this->phpAssoc($fields, 2)};
        parent::__construct();
    }
}

PHP;
    }

    protected function indexVue(array $descriptor, array $names, string $i18nPath, string $apiUrl, string $menuName): string
    {
        $columns = $this->tableColumns($descriptor, $i18nPath);
        $defaultItems = $this->defaultItems($descriptor['fields']);
        $notEdit = $this->dblClickNotEditColumn($descriptor);
        $defaultOrder = $descriptor['sortField']
            ? "defaultOrder: { prop: '{$descriptor['sortField']}', order: 'desc' },"
            : "defaultOrder: { prop: '{$descriptor['pk']}', order: 'desc' },";
        $optButtons = $descriptor['sortField'] ? "['sort', 'edit', 'delete']" : "['edit', 'delete']";
        $dragSort = $descriptor['sortField'] ? "\n        xTable.dragSort()" : '';

        return <<<VUE
<template>
    <div class="default-main x-table-box">
        <el-alert class="x-table-alert" v-if="xTable.table.remark" :title="xTable.table.remark" type="info" show-icon />

        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('{$i18nPath}.quick Search Fields') })"
        />

        <Table ref="tableRef" />

        <Form />
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import Form from './form.vue'
import { xTableApi } from '/@/api/common'
import { defaultOptButtons } from '/@/components/table'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import xTableClass from '/@/utils/xTable'

defineOptions({
    name: '{$menuName}',
})

const { t } = useI18n()
const tableRef = useTemplateRef('tableRef')
const optButtons: OptButton[] = defaultOptButtons({$optButtons})

const xTable = new xTableClass(
    new xTableApi('{$apiUrl}'),
    {
        pk: '{$descriptor['pk']}',
        column: [
{$columns}
        ],
        dblClickNotEditColumn: {$notEdit},
        {$defaultOrder}
    },
    {
        defaultItems: {$defaultItems},
    }
)

provide('xTable', xTable)

onMounted(() => {
    xTable.table.ref = tableRef.value
    xTable.mount()
    xTable.getData()?.then(() => {
        xTable.initSort(){$dragSort}
    })
})
</script>

<style scoped lang="scss"></style>
VUE;
    }

    protected function formVue(array $descriptor, string $i18nPath): string
    {
        $items = $this->formItems($descriptor, $i18nPath);
        $rules = $this->formRules($descriptor, $i18nPath);
        $validatorImport = $rules ? "\nimport { buildValidatorData } from '/@/utils/validate'" : '';

        return <<<VUE
<template>
    <el-dialog
        class="x-operate-dialog"
        :close-on-click-modal="false"
        :destroy-on-close="true"
        :model-value="['Add', 'Edit'].includes(xTable.form.operate!)"
        @close="xTable.toggleForm"
    >
        <template #header>
            <div class="title" v-drag="['.x-operate-dialog', '.el-dialog__header']" v-zoom="'.x-operate-dialog'">
                {{ xTable.form.operate ? t(xTable.form.operate) : '' }}
            </div>
        </template>
        <el-scrollbar v-loading="xTable.form.loading" class="x-table-form-scrollbar">
            <div
                class="x-operate-form"
                :class="'x-' + xTable.form.operate + '-form'"
                :style="config.layout.shrink ? '' : 'width: calc(100% - ' + xTable.form.labelWidth! / 2 + 'px)'"
            >
                <el-form
                    ref="formRef"
                    @keyup.enter="xTable.onSubmit(formRef)"
                    :model="xTable.form.items"
                    :label-position="config.layout.shrink ? 'top' : 'right'"
                    :label-width="xTable.form.labelWidth + 'px'"
                    :rules="rules"
                    v-if="!xTable.form.loading"
                >
{$items}
                </el-form>
            </div>
        </el-scrollbar>
        <template #footer>
            <div :style="'width: calc(100% - ' + xTable.form.labelWidth! / 1.8 + 'px)'">
                <el-button @click="xTable.toggleForm('')">{{ t('Cancel') }}</el-button>
                <el-button v-blur :loading="xTable.form.submitLoading" @click="xTable.onSubmit(formRef)" type="primary">
                    {{ xTable.form.operateIds && xTable.form.operateIds.length > 1 ? t('Save and edit next item') : t('Save') }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { reactive, inject, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import type xTableClass from '/@/utils/xTable'
import FormItem from '/@/components/formItem/index.vue'
import type { FormItemRule } from 'element-plus'
import { useConfig } from '/@/stores/config'{$validatorImport}

const config = useConfig()
const formRef = useTemplateRef('formRef')
const xTable = inject('xTable') as xTableClass

const { t } = useI18n()

const rules: Partial<Record<string, FormItemRule[]>> = reactive({{$rules}
})
</script>

<style scoped lang="scss"></style>
VUE;
    }

    protected function langFile(array $descriptor, string $i18nPath, string $locale): string
    {
        $items = [
            'title' => $locale === 'en' ? $descriptor['path'] : $descriptor['title'],
            'quick Search Fields' => implode('/', $descriptor['quickSearch']),
        ];
        foreach ($descriptor['fields'] as $field) {
            $items[$field['name']] = $locale === 'en' ? $field['enLabel'] : $field['label'];
            foreach ($field['dict'] as $key => $value) {
                $items[$field['name'] . ' ' . $key] = $locale === 'en' ? $this->translateDictValue((string) $value) : $value;
            }
        }

        $body = '';
        foreach ($items as $key => $value) {
            $body .= '    ' . $this->tsObjectKey($key) . ': ' . $this->tsString((string) $value) . ",\n";
        }
        return "export default {\n{$body}}\n";
    }

    protected function tableColumns(array $descriptor, string $i18nPath): string
    {
        $columns = ["            { type: 'selection', align: 'center' }"];
        foreach ($descriptor['fields'] as $field) {
            if (!$field['tableVisible']) {
                continue;
            }

            $parts = [
                'label' => "t('{$i18nPath}.{$field['name']}')",
                'prop' => $this->tsString($field['name']),
                'align' => "'center'",
            ];
            if ($field['name'] === $descriptor['pk']) {
                $parts['width'] = "'80'";
                $parts['sortable'] = "'custom'";
                $parts['operator'] = "'RANGE'";
            } elseif ($field['operator'] === false) {
                $parts['operator'] = 'false';
            } elseif ($field['operator']) {
                $parts['operator'] = "'" . $field['operator'] . "'";
            }
            if ($field['tableRender']) {
                $parts['render'] = "'" . $field['tableRender'] . "'";
            }
            if ($field['dict']) {
                $parts['replaceValue'] = $this->tsI18nDict($field, $i18nPath);
                $parts['custom'] = $this->tsTagTypes($field['dict']);
            }

            $columns[] = '            { ' . $this->joinTsProps($parts) . ' }';
        }

        $columns[] = "            { label: t('Operate'), align: 'center', width: '130', render: 'buttons', buttons: optButtons }";
        return implode(",\n", $columns);
    }

    protected function formItems(array $descriptor, string $i18nPath): string
    {
        $items = [];
        foreach ($descriptor['fields'] as $field) {
            if (!$field['formVisible']) {
                continue;
            }

            $attrs = $this->formInputAttr($field, $i18nPath);
            $inputAttr = $attrs ? "\n                        :input-attr=\"{$attrs}\"" : '';
            $items[] = <<<VUE
                    <FormItem
                        type="{$field['input']}"
                        prop="{$field['name']}"
                        :label="t('{$i18nPath}.{$field['name']}')"
                        v-model="xTable.form.items!['{$field['name']}']"{$inputAttr}
                    />
VUE;
        }
        return implode("\n", $items);
    }

    protected function formRules(array $descriptor, string $i18nPath): string
    {
        $rules = [];
        foreach ($descriptor['fields'] as $field) {
            if (!$field['formVisible'] || !$field['required']) {
                continue;
            }
            $rules[] = "\n    '{$field['name']}': [buildValidatorData({ name: 'required', title: t('{$i18nPath}.{$field['name']}') })],";
        }
        return implode('', $rules);
    }

    protected function defaultItems(array $fields): string
    {
        $items = [];
        foreach ($fields as $field) {
            if (!$field['formVisible'] || $field['default'] === null) {
                continue;
            }
            $items[$field['name']] = (string) $field['default'];
        }
        return $this->tsValue($items);
    }

    protected function dblClickNotEditColumn(array $descriptor): string
    {
        $fields = ['undefined'];
        foreach ($descriptor['fields'] as $field) {
            if ($field['input'] === 'switch') {
                $fields[] = "'" . $field['name'] . "'";
            }
        }
        return '[' . implode(', ', $fields) . ']';
    }

    protected function formInputAttr(array $field, string $i18nPath): string
    {
        $attrs = [];
        if ($field['dict']) {
            $attrs['content'] = $this->tsI18nDict($field, $i18nPath);
            if (in_array($field['input'], ['radio', 'checkbox'], true)) {
                $attrs['border'] = 'true';
            }
        }
        if ($field['input'] === 'switch') {
            $attrs['activeValue'] = "'1'";
            $attrs['inactiveValue'] = "'0'";
        }
        if ($field['input'] === 'textarea') {
            $attrs['autosize'] = '{ minRows: 3, maxRows: 6 }';
        }
        if ($field['input'] === 'remoteSelect') {
            $labelField = preg_replace('/_id$/', '', $field['name']);
            $attrs['field'] = "'" . ($labelField ?: 'name') . "'";
        }
        if (!$attrs) {
            return '';
        }
        return '{ ' . $this->joinTsProps($attrs) . ' }';
    }

    protected function modelTypeCode(array $fields): string
    {
        $types = [];
        foreach ($fields as $field) {
            if ($field['modelType']) {
                $types[$field['name']] = $field['modelType'];
            }
        }
        if (!$types) {
            return '';
        }
        return "\n    protected \$type = " . $this->phpAssoc($types, 1) . ";\n";
    }

    protected function tsI18nDict(array $field, string $i18nPath): string
    {
        $items = [];
        foreach ($field['dict'] as $key => $value) {
            $items[$key] = "t('{$i18nPath}.{$field['name']} {$key}')";
        }
        return '{ ' . $this->joinTsProps($items) . ' }';
    }

    protected function tsTagTypes(array $dict): string
    {
        $types = ['success', 'danger', 'warning', 'info'];
        $items = [];
        $index = 0;
        foreach ($dict as $key => $value) {
            $items[$key] = "'" . ($key === '1' ? 'success' : ($key === '0' ? 'danger' : $types[$index % count($types)])) . "'";
            $index++;
        }
        return '{ ' . $this->joinTsProps($items) . ' }';
    }

    protected function joinTsProps(array $props): string
    {
        $items = [];
        foreach ($props as $key => $value) {
            $items[] = $this->tsObjectKey((string) $key) . ': ' . $value;
        }
        return implode(', ', $items);
    }

    protected function tsValue(mixed $value): string
    {
        if (is_array($value)) {
            $items = [];
            foreach ($value as $key => $item) {
                $items[] = $this->tsObjectKey((string) $key) . ': ' . $this->tsValue($item);
            }
            return '{ ' . implode(', ', $items) . ' }';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if ($value === null) {
            return 'null';
        }
        return $this->tsString((string) $value);
    }

    protected function tsObjectKey(string $key): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) ? $key : $this->tsString($key);
    }

    protected function tsString(string $value): string
    {
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
    }

    protected function phpArray(array $items, int $indent = 1): string
    {
        if (!$items) {
            return '[]';
        }
        $values = array_map(fn($item) => "'" . $this->phpString((string) $item) . "'", $items);
        return '[' . implode(', ', $values) . ']';
    }

    protected function phpAssoc(array $items, int $indent = 1): string
    {
        if (!$items) {
            return '[]';
        }
        $pad = str_repeat('    ', $indent);
        $endPad = str_repeat('    ', max(0, $indent - 1));
        $body = '';
        foreach ($items as $key => $value) {
            $body .= "\n{$pad}'" . $this->phpString((string) $key) . "' => '" . $this->phpString((string) $value) . "',";
        }
        return '[' . $body . "\n{$endPad}]";
    }

    protected function phpString(string $value): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
    }

    protected function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        return str_replace(' ', '', ucwords($value));
    }

    protected function translateDictValue(string $value): string
    {
        return match ($value) {
            '启用' => 'Enable',
            '禁用' => 'Disable',
            '开启' => 'Enable',
            '关闭' => 'Disable',
            default => $value,
        };
    }
}
