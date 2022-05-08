const app = Vue.createApp({});

app.component('app-home', {
    data() {
        let config = `            
            ;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
            ;;;; 默认值
            
            [global]
            
            ; 节点名称
            name = default
            
            ; 节点外网地址
            ip = 1.2.3.4
            
            ; 节点监听端口/UDP
            port = 2355
            
            ; 节点虚拟设备地址
            vip = 172.21.0.1/22
            
            ; 允许访问的地址列表
            acl =
            
            ; 默认假设节点在NAT网络中
            alive = 25
            
            ; 指定互通节点列表
            ;peers[] = nod11
            ;peers[] = nod21
            
            ;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
            ;;;; 区域-1
            
            [nod11]
            
            ip = 11.11.11.11
            
            [nod12]
            
            ip = 12.12.12.12
            
            ;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
            ;;;; 区域-2
            
            [nod21]
            
            ip = 21.21.21.21
            
            [nod22]
            
            ip = 22.22.22.22
            
            ;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
            ;;;; 区域-3
            
            [nod31]
            
            ip = 31.31.31.31
            
            [nod32]
            
            ip = 32.32.32.32
        `;
        return {
            doing: false,
            config: config.trim().replace(/\n\s+/g, '\n'),
            iturl: location.origin + location.pathname,
            items: []
        };
    },
    methods: {
        build() {
            this.doing = true;
            const config = this.config.replace(/;.+\n/g, '');
            const request = new Request('api/?config=1', {
                method: 'POST',
                body: btoa(config)
            });
            fetch(request)
                .then(response => response.json())
                .then(data => {
                    this.items = data.wglist || [];
                    this.doing = false;
                });
        }
    },
    template: `
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid justify-content-start">
                <a class="navbar-brand" href="https://www.rehiy.com/wireguard">
                    WireGuard 批量部署工具
                </a>
            </div>
        </nav>
        <div class="container-xxl mt-3">
            <div class="row align-items-start">
                <div class="col-12 col-md-7">
                    <div class="mt-3">
                        <textarea class="form-control lh-lg" v-model="config"></textarea>
                    </div>
                </div>
                <div class="col-12 col-md-5">
                    <div class="mt-3">
                        <button class="form-control btn btn-secondary" v-if="doing">Loading</button>
                        <button class="form-control btn btn-primary" @click="build()" v-else>生成部署命令</button>
                    </div>
                    <div class="card mt-3" v-for="item in items">
                        <div class="card-body">
                            <h5 class="card-title">{{item.ip}}</h5>
                            <p class="card-text">
                                wget {{iturl}}{{item.dir}}/alpine | sh -
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-3"></div>
    `
});

app.mount('app-root');
