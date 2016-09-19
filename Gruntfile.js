module.exports = function(grunt) {
  // Project configuration.
  grunt.initConfig({
    wp_readme_to_markdown: {
      target: {
        files: {
          'readme.md': 'readme.txt'
        },
      },
    },
    replace: {
      dist: {
        options: {
          patterns: [
            {
              match: /^/,
              replacement: '[![Build Status](https://travis-ci.org/pfefferle/wordpress-webmention.svg?branch=master)](https://travis-ci.org/pfefferle/wordpress-webmention) \n\n'
            }
          ]
        },
        files: [
          {
            src: ['README.md'],
            dest: './'
          }
        ]
      }
    }
  });

  grunt.loadNpmTasks('grunt-wp-readme-to-markdown');
  grunt.loadNpmTasks('grunt-replace');

  // Default task(s).
  grunt.registerTask('default', ['wp_readme_to_markdown', 'replace']);
};
