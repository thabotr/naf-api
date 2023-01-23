import unittest
import sys
import getopt

from connections_test import *
from messages_test import *
from profiles_test import *
from chats_test import *
from notifications_test import *
from routes import Routes


def process_option(opt):
    if opt == '-h':
        print(f'''
    Usage: {sys.argv[0]} [option]

    Options:
      -p, --production      Run tests against the production server
    ''')
        sys.exit()
    elif opt in ('-p', '--production'):
        Routes.RUN_AGAINST_PROD = True

def split_args():
    test_args, program_args = [], []
    is_test_arg = False
    for arg in sys.argv:
      if arg == '--':
        is_test_arg = True
      elif is_test_arg:
        test_args.append(arg)
      else:
        program_args.append(arg)
    test_args = [sys.argv[0]] + test_args
    return test_args, program_args

def main():
    test_args, prog_args = split_args()
    sys.argv = test_args

    if '-h' in test_args or '--help' in test_args:
      return

    opts, _ = getopt.getopt(prog_args[1:], "hp", ["production"])
    for opt, _ in opts:
        process_option(opt)
    host = Routes.PROD_SERVER if Routes.RUN_AGAINST_PROD else f"{Routes.HOST}:{Routes.PORT}"
    server_url = f"{host}{Routes.BASE_PATH}"
    print(f'Running tests against {server_url}')


if __name__ == "__main__":
    main()
    unittest.main()
