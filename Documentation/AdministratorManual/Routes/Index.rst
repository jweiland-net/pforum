..  include:: /Includes.rst.txt

..  _routes:

======
Routes
======

..  versionadded:: 8.0.0

    Topics and posts have a path segment (column `path_segment`).

The path segment of a topic or post is generated from its title, when the
record is created in the frontend. It is unique in the folder of the
record. Published records keep their path segment, so their URL is kept.
Run the upgrade wizard "[pforum] Fill path segments of topics and posts"
to fill the path segments of existing records.

Forum records have no path segment, so their UID is part of the URL.

..  code-block:: yaml
    :caption: config/sites/<your-site>/config.yaml

    routeEnhancers:
      PforumPlugin:
        type: Extbase
        extension: Pforum
        plugin: Forum
        routes:
          - routePath: '/'
            _controller: 'Forum::list'
          - routePath: '/forum/{forum}'
            _controller: 'Forum::show'
          - routePath: '/forum/{forum}/page/{currentPage}'
            _controller: 'Forum::show'
          - routePath: '/topic/{topic}'
            _controller: 'Topic::show'
          - routePath: '/topic/{topic}/page/{currentPage}'
            _controller: 'Topic::show'
        defaultController: 'Forum::list'
        requirements:
          forum: '\d+'
        aspects:
          topic:
            type: PersistedAliasMapper
            tableName: tx_pforum_domain_model_topic
            routeFieldName: path_segment
          currentPage:
            type: StaticRangeMapper
            start: '1'
            end: '1000'

The `PersistedAliasMapper` resolves visible topics only. The actions to
create, edit, activate and delete records keep their query parameters,
as they work with hidden records and tokens.
