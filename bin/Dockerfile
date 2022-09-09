FROM debian:11

RUN echo "deb http://deb.debian.org/debian bullseye-backports main contrib non-free" >> /etc/apt/sources.list \
    && echo "deb-src http://deb.debian.org/debian bullseye-backports main contrib non-free" >> /etc/apt/sources.list \
    && apt-get update -yq \
    && apt-get install -yqq \
        git \
        git-filter-repo \
        vim \
            openssh-client \
    && mkdir -p ~/.ssh/known_hosts \
    && chmod 700 ~/.ssh/ \
    && chmod 600 ~/.ssh/* \
    && chown -R ${USER} ~/.ssh/ \
    && chgrp -R ${USER} ~/.ssh/ \
    && ssh-keyscan -t rsa github.com >> ~/.ssh/known_hosts
