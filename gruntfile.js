module.exports = function (grunt) {
  'use strict';
  var banner = '/**\n * <%= pkg.homepage %>\n * Copyright (c) <%= grunt.template.today("yyyy") %>\n * This file is generated automatically. Do not edit.\n */\n';
  // Project configuration
  grunt.initConfig({
    pkg:    grunt.file.readJSON( 'package.json' ),
    watch: {
        styles: {
            files: "css/*.scss",
            tasks: ['sass', 'postcss'],
        },
        javascript: {
            files: ["js/*.js", "!js/*.min.js"],
            tasks: ['uglify'],
        },
    },
    uglify: {
        custom: {
            files: {
                'js/cf7-authorize-backend.min.js': 'js/cf7-authorize-backend.js',
                'js/chosen.jquery.min.js': 'js/chosen.jquery.js'
            },
        },
    },
    sass: {
        dist: {
            options: {
                style: 'compressed'
            },
            files: {
                'css/cf7-authorize.min.css': 'css/cf7-authorize.scss',
                'css/chosen.min.css': 'css/chosen.scss'
            }
        }
    },
    postcss: {
        options: {
            map: {
                inline: false,
                annotation: 'css/',
            },

            processors: [
                require('pixrem')(), // add fallbacks for rem units
                require('autoprefixer')({browsers: 'last 2 versions'}), // add vendor prefixes
                require('cssnano')() // minify the result
            ]
        },
        dist: {
            src: 'css/*.min.css',
        }
    },
    browserSync: {
        dev: {
            bsFiles: {
                src : ['**/*.css', '**/*.php', '**/*.js', '!node_modules'],
            },
            options: {
                watchTask: true,
                proxy: "http://dev.abc.dev",
            },
        },
    },
    addtextdomain: {
        options: {
            textdomain: 'woocommerce-customers-robly',
        },
        target: {
            files: {
                src: [ '*.php', '**/*.php', '!node_modules/**', '!php-tests/**', '!bin/**' ]
            }
        }
    },
    wp_readme_to_markdown: {
        your_target: {
            files: {
                'README.md': 'readme.txt'
            }
        },
    },
    makepot: {
        target: {
            options: {
                domainPath: '/languages',
                mainFile: 'cf7-authorize-net.php',
                potFilename: 'cf7-authorize-net.pot',
                potHeaders: {
                    poedit: true,
                    'x-poedit-keywordslist': true
                },
                type: 'wp-plugin',
                updateTimestamp: true
            }
        }
    },
  });

    grunt.loadNpmTasks('grunt-sass');
    grunt.loadNpmTasks('grunt-postcss');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-browser-sync');
    grunt.loadNpmTasks( 'grunt-wp-i18n' );
    grunt.loadNpmTasks( 'grunt-wp-readme-to-markdown' );
    grunt.registerTask( 'i18n', ['addtextdomain', 'makepot'] );
    grunt.registerTask( 'readme', ['wp_readme_to_markdown']);
    grunt.registerTask('default', [
        'browserSync',
        'watch',
    ]);
    grunt.util.linefeed = '\n';
};
