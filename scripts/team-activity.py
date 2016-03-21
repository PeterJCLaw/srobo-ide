#!/usr/bin/env python3

from argparse import ArgumentParser
from collections import namedtuple
from datetime import datetime, MINYEAR, tzinfo
import os
from subprocess import check_output

REPO_ROOT = os.path.join(os.path.dirname(__file__), '../repos')

TeamProgress = namedtuple('TeamProgress', ['tla', 'when', 'project', 'relative'])

error_marker = object()

def get_latest(tla):
    best_dt = datetime.min
    latest_proj = None
    best_relative = None
    try:
        master_dir = os.path.join(REPO_ROOT, tla, 'master')
        projects = os.listdir(master_dir)
        for proj in projects:
            try:
                #print(proj)
                proj_dir = os.path.join(master_dir, proj)
                out_bytes = check_output(['git', 'show', '-s', '--format=format:%ci|%cr'], cwd=proj_dir)
                out = out_bytes.decode('utf-8')
                #print(out)
                date_str, relative = out.split('|')
                #print(date_str, relative)
                dt = datetime.strptime(date_str, '%Y-%m-%d %H:%M:%S %z')
                dt = dt.replace(tzinfo = None)
                #print(dt)

                if best_dt is None or dt > best_dt:
                    best_dt = dt
                    latest_proj = proj
                    best_relative = relative
            except:
                pass
    except:
        global error_marker
        latest_proj = error_marker
        best_relative = error_marker

    return TeamProgress(tla, best_dt, latest_proj, best_relative)

def print_info(tp):
    if tp.project is error_marker:
        print("Failed to get project times for {0}.".format(tp.tla))
    elif tp.project:
        d = tp._asdict()
        d['when'] = tp.when.date()
        print("{tla}: {project} on {when}, {relative}".format(**d))
    else:
        print("{0} have never edited a project".format(tp.tla))

if __name__ == '__main__':
    parser = ArgumentParser(description = "Prints information about team's activity by inspecting their git repos")
    parser.add_argument('--sort',
                        choices=('when', 'tla'),
                        default="tla",
                        help="sort the output by the given property")
    args = parser.parse_args()

    teams = [item for item in os.listdir(REPO_ROOT) \
                    if os.path.isdir(os.path.join(REPO_ROOT, item))]

    info = map(get_latest, teams)

    if args.sort == 'when':
        sorted_info = sorted(info, key = lambda tp: datetime.now() - tp.when)
    else:
        # TLA
        sorted_info = sorted(info, key = lambda tp: tp.tla)

    for tp in sorted_info:
        print_info(tp)
