//<editor-fold desc="Import">
import {default as gulp} from "gulp";
import {default as _plug_babel} from 'gulp-babel';
import {default as _plug_debug} from 'gulp-debug';
import {default as _plug_filter} from 'gulp-filter';
import {default as _plug_flatmap} from 'gulp-flatmap';
import {default as _plug_less} from 'gulp-less';
import {default as _plug_plumber} from 'gulp-plumber';
import {default as _plug_rename} from 'gulp-rename';
import {default as _plug_sourcemaps} from 'gulp-sourcemaps';
import {default as _plug_stringReplace} from 'gulp-string-replace';
import {default as _plug_typescript} from 'gulp-typescript';
import {default as _plug_uglifyCss} from 'gulp-clean-css';
import {default as _plug_uglifyJsEs} from 'gulp-uglify-es';
import {default as _plug_watch} from 'gulp-watch';
import {default as _plug_path} from 'path';

const plug = {
    babel: _plug_babel,
    debug: _plug_debug,
    filter: _plug_filter,
    flatmap: _plug_flatmap,
    less: _plug_less,
    plumber: _plug_plumber,
    rename: _plug_rename,
    sourcemaps: _plug_sourcemaps,
    stringReplace: _plug_stringReplace,
    typescript: _plug_typescript,
    uglifyCss: _plug_uglifyCss,
    uglifyJsEs: _plug_uglifyJsEs.default,
    watch: _plug_watch,
};
const tools = {
    path: _plug_path,
}
//</editor-fold>

const basePath = 'public/';
const lessPath = basePath + 'less/**/*.less';
const typeScriptPath = [basePath + 'ts/**/*.ts', basePath + 'ts/**/*.tsx'];

const uglifyCssOptions = {
    level: {
        2: {
            mergeSemantically: true,
            restructureRules: true,
        }
    }
};
const typescriptOptions = {
    isolatedModules: true,
    jsx: 'react',
    jsxFactory: 'JsxFactory.createElement',
    lib: ["DOM", "ES6", "ScriptHost", "DOM.Iterable", "es2017"],
    module: "none",
    moduleResolution: "node",
    rootDir: basePath + 'ts/',
    target: "ES2021",
};

//<editor-fold desc="LESS">
//const lessMain = (s) => s
const lessSingle = (stream, file) => {
    const base = tools.path.dirname(file.path);
    return gulp.src(file.path, {base, cwd: base})
        .pipe(plug.debug({title: 'less2:'}))
        .pipe(plug.plumber())
        .pipe(plug.sourcemaps.init({loadMaps: false}))
        .pipe(plug.less({}))
        // TODO fix path of sources, and disable includeContent
        .pipe(plug.sourcemaps.write('./', {includeContent: true}))
        .pipe(gulp.dest(base))
        .pipe(plug.filter(['*', '!*.map']))
        .pipe(plug.debug({title: 'css:'}))
        .pipe(plug.uglifyCss(uglifyCssOptions, null))
        .pipe(plug.rename({extname: '.min.css'}, {}))
        // TODO fix path of sources, and disable includeContent
        .pipe(plug.sourcemaps.write('./', {includeContent: true}))
        .pipe(gulp.dest(base))
        .pipe(plug.filter(['*', '!*.map']))
        .pipe(plug.debug({title: 'css-min:'}))
        ;
}
const lessTask = () => gulp.src(lessPath, {base: basePath})
    .pipe(plug.debug({title: 'less:'}))
    .pipe(plug.flatmap(lessSingle));
const lessWatch = () => plug.watch(lessPath, {base: basePath})
    .pipe(plug.debug({title: 'less:'}))
    .pipe(plug.flatmap(lessSingle));
gulp.task("less", lessTask);
gulp.task("watch-less", lessWatch);
//</editor-fold>

//<editor-fold desc="Typescript">
const tsSingle = (isolatedModules) => (stream, file) => {
    const base = tools.path.dirname(file.path);
    const typescriptOptionsVariant = {...typescriptOptions, isolatedModules};
    return gulp.src(file.path, {base, cwd: base})
        .pipe(plug.plumber())
        .pipe(plug.sourcemaps.init({}))
        .pipe(plug.typescript.createProject(typescriptOptionsVariant)())
        .pipe(plug.stringReplace('const UserScriptHeader = `// ==UserScript==', '// ==UserScript=='))
        .pipe(plug.stringReplace('// ==/UserScript==`;', '// ==/UserScript=='))
        // TODO fix path of sources, and disable includeContent
        .pipe(plug.sourcemaps.write('./', {includeContent: true}))
        .pipe(gulp.dest(base))
        .pipe(plug.filter(['*', '!*.map']))
        .pipe(plug.debug({title: 'js:'}))
        .pipe(plug.babel({
            compact: false,
            presets: [["@babel/env", {
                "targets": {
                    browsers: [
                        'last 2 Chrome versions',
                        'last 2 Firefox versions',
                    ],
                },
            }]],
        }))
        .pipe(plug.uglifyJsEs())
        .pipe(plug.rename({extname: '.min.js'}, {}))
        // TODO fix path of sources, and disable includeContent
        .pipe(plug.sourcemaps.write('./', {includeContent: true}))
        .pipe(gulp.dest(base))
        .pipe(plug.filter(['*', '!*.map']))
        .pipe(plug.debug({title: 'js-min:'}));
    ;
}

const tsMain = (s, isolatedModules) => s
    .pipe(plug.debug({title: 'ts:'}))
    .pipe(plug.flatmap(tsSingle(isolatedModules)))
const tsTaskFast = () => tsMain(gulp.src(typeScriptPath, {base: basePath}), true);
const tsTaskFull = () => tsMain(gulp.src(typeScriptPath, {base: basePath}), false);
const tsWatch = () => tsMain(plug.watch(typeScriptPath, {base: basePath}), false);
gulp.task("ts", tsTaskFast);
gulp.task("ts-full", tsTaskFull);
gulp.task("watch-ts", tsWatch);
//</editor-fold>

gulp.task("default", gulp.parallel(['less', 'ts']));
gulp.task("watch-all", gulp.parallel(['watch-less', 'watch-ts']));
gulp.task("watch", gulp.series(['default', 'watch-all']));
