<?php

declare(strict_types=1);

/*
 * 初始化：建表、创建管理员账号。可以重复执行，已有的数据不会被覆盖。
 *
 *   php bin/install.php                 # 默认账号 admin，密码随机生成并打印
 *   php bin/install.php admin 12345678  # 指定账号和密码
 */

use App\Model\User;
use Illuminate\Database\Capsule\Manager as Capsule;

require __DIR__ . '/../bootstrap/app.php';

if (!preg_match('/^[0-9a-fA-F]{64}$/', $_ENV['PASSWORD_KEY'] ?? '')) {
    fwrite(STDERR, ".env 里的 PASSWORD_KEY 没配置，先生成一个：php -r 'echo bin2hex(random_bytes(32));'\n");
    exit(1);
}

$pdo = Capsule::connection()->getPdo();
$sql = file_get_contents(BASE_PATH . '/database/schema.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    $pdo->exec($statement);
}
// 升级：平台加分类字段
$columns = array_column($pdo->query('SHOW COLUMNS FROM `platforms`')->fetchAll(PDO::FETCH_ASSOC), 'Field');
if (!in_array('category', $columns, true)) {
    $pdo->exec("ALTER TABLE `platforms` ADD `category` varchar(50) NOT NULL DEFAULT '' COMMENT '分类，空表示未分类' AFTER `title`, ADD KEY `idx_category` (`category`)");
    echo "已升级：platforms 增加分类字段 category\n";
}
echo "数据表已就绪\n";

if (!User::query()->exists()) {
    $username = $argv[1] ?? 'admin';
    $password = $argv[2] ?? bin2hex(random_bytes(6));
    $user = new User(['username' => $username, 'name' => '管理员', 'is_admin' => true, 'status' => User::STATUS_ENABLED]);
    $user->setPassword($password);
    $user->save();
    echo "已创建管理员账号：{$username}  密码：{$password}（登录后请修改）\n";
}

echo "初始化完成\n";
