FROM caddy:2.11.7-alpine

# The official image runs as root. Run as an unprivileged caddy user instead, with a uid/gid
# above 10000 like the IDE's other servers (nginx 10014, apache 10015). The caddy binary
# already carries cap_net_bind_service, so it can still listen on 443 inside the container.
RUN addgroup -S -g 10016 caddy && \
    adduser -S -D -H -u 10016 -G caddy -s /sbin/nologin caddy && \
    chown -R caddy:caddy /config /data

USER caddy
