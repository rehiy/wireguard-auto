# WireGuard Auto Config Tools

- 批量生成 WireGuard 配置文件（Mesh模式）
- 支持局域网和公网多地域混合组网
- 支持一键部署

## 离线模式

  - Windows 系统，下载项目源码，修改`config.ini`
  - 双击`build.bat`运行，自动生成配置文件到`deploy`目录下

## 在线部署模式

  ```
  docker run -d -p 80:80 rehiy/wireguard-auto
  ```

  > 浏览器访问 http://your-docker-ip
