<?php
namespace plugin\api\{$module};

{$name}
class {$classname}{
	/*最终数据配置*/
	public $_content=<<<EOF
{$content}
EOF;
	/*操作流程配置*/
	public $_ops={$ops};
}