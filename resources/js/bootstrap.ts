import _ from 'lodash';
import axios from 'axios';

declare global {
    interface Window {
        _: typeof _;
        axios: typeof axios;
    }
}

window._ = _;
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
