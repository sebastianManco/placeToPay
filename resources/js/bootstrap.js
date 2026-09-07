import _ from 'lodash';
window._ = _;

import * as Popper from 'popper.js';
window.Popper = Popper;

import jQuery from 'jquery';
window.$ = window.jQuery = jQuery;

import 'bootstrap';

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
