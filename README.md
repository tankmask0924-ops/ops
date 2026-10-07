# 平台账号管理后台

管理各个平台（网站）的登录账号密码，按人分配：每个人只能看到分配给自己的平台。

- 后端：PHP 8.2+ + [Slim 4](https://www.slimframework.com/) + illuminate/database，MySQL
- 前端：Vite + Vue 3 + TypeScript + Element Plus（`web/`），打包到 `public/admin`，由 PHP 直接提供

## 功能

| 菜单 | 谁能用 | 说明 |
|---|---|---|
| 平台 | 所有人 | 卡片展示平台：标题、网址、账号、密码、网站描述。**点卡片直接在新标签页打开网址**；账号可一键复制，密码默认隐藏，点眼睛查看、可一键复制。管理员能看到全部平台，并能新增 / 编辑 / 删除、选择可见人员；普通账号只能看到分配给自己的平台，只读 |
| 账号管理 | 管理员 | 新增 / 编辑 / 禁用 / 删除账号，设置是否管理员，分配可见平台 |
| 密码查看记录 | 管理员 | 每次查看或复制平台密码都会记录：谁、哪个平台、什么时间、IP |

- 可见平台在两个地方都能设置：编辑平台时选「可见人员」，或编辑账号时选「可见平台」，改的是同一份数据
- 网址只填域名时，保存时自动补上 `https://`
- 至少保留一个启用的管理员；不能禁用 / 删除自己，不能取消自己的管理员
- 登录：连续输错 5 次锁定 15 分钟；修改 / 重置密码后，该账号其他地方的登录全部失效

## 平台密码的存储

平台密码**不存明文**，用 AES-256-GCM 加密后存在 `platforms.password_encrypted`，密钥是 `.env` 里的 `PASSWORD_KEY`。
数据库单独泄露也拿不到真实密码。

> **`PASSWORD_KEY` 一定要备份好。** 有数据之后不能更换，丢了或换了，已保存的密码全部无法解密。

## 服务器部署（Nginx + PHP-FPM，不用 Docker）

环境：PHP 8.2+（php-fpm，扩展 `pdo_mysql`、`openssl`）、Composer、Nginx、MySQL 5.7+。
Node.js 只在打包前端时用，可以在本机打包好再上传 `public/admin`。

```bash
# 1. 上传代码到服务器，例如 /www/ops（web/node_modules 不用传）

# 2. 安装 PHP 依赖
cd /www/ops
composer install --no-dev --optimize-autoloader

# 3. 配置
cp .env.example .env
#    填 DB_*；JWT_SECRET 和 PASSWORD_KEY 各生成一个：php -r 'echo bin2hex(random_bytes(32));'
#    APP_DEBUG 保持 false

# 4. 初始化数据库（建表、建管理员；可以重复执行，已有数据不会被覆盖）
php bin/install.php admin '初始密码'

# 5. 前端打包（输出到 public/admin；也可以在本机打包后只上传 public/admin）
cd web && npm ci && npm run build && cd ..

# 6. Nginx：参考 deploy/nginx.conf，改 server_name、root、fastcgi_pass；网站运行目录是 public
```

宝塔面板：新建站点，运行目录选 `/public`，伪静态 / 配置文件按 `deploy/nginx.conf` 的几个 `location` 填。

**建议上 HTTPS**：页面上传输的是明文密码；另外浏览器只在 HTTPS 下允许网页写剪贴板（HTTP 下会退回到兼容方式复制）。

## 本地开发

```bash
cp .env.example .env    # DB_HOST=mysql，DB_USERNAME=ops，DB_PASSWORD=ops，再填 JWT_SECRET / PASSWORD_KEY
docker compose up -d    # PHP 内置服务器 :9505 + MySQL :3307
docker compose exec ops composer install
docker compose exec ops php bin/install.php admin '初始密码'
cd web && npm install && npm run dev    # http://localhost:5178/admin/ ，/admin-api 代理到 :9505
```

## 目录

```
bin/install.php        初始化：建表、建管理员
config/routes.php      接口路由
database/schema.sql    表结构
deploy/nginx.conf      Nginx 配置示例
public/                网站运行目录（index.php + 打包后的前端 admin/）
src/                   后端代码（Controller / Middleware / Model / Service / Support）
web/                   前端源码
```
